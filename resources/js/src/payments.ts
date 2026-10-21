/**
 * Card collection for gateways that tokenise in the browser, mirroring payregister's
 * process.stripe.js, process.square.js and authorizenetaim.js without jQuery.
 */

import { t } from './lang';

export interface PaymentClient {
    type: 'stripe' | 'square' | 'authorizenet';
    hidden: Record<string, string>;
    profile: { brand: string; last4: string } | null;
    canSaveCard: boolean;
    // Stripe
    publishableKey?: string;
    clientSecret?: string | null;
    options?: Record<string, unknown>;
    // Square
    sdkUrl?: string;
    applicationId?: string;
    locationId?: string;
    amount?: string;
    currency?: string;
    // Authorize.Net
    endpoint?: string;
    apiLoginId?: string;
    clientKey?: string;
}

export interface Buyer {
    firstName: string;
    lastName: string;
    email: string;
}

export interface CardGateway {
    mount(element: HTMLElement): Promise<void>;
    /** Check the card fields before the order is saved. */
    check(): Promise<void>;
    /** Charge or tokenise the card; returns the fields payregister reads on submit. */
    collect(client: PaymentClient, buyer: Buyer): Promise<Record<string, string>>;
    /** Gateways with a pop-up form can open it before checkout; resolves to a card label. */
    open?(): Promise<string>;
    destroy(): void;
}

const scripts = new Map<string, Promise<void>>();

export function loadScript(src: string, fresh = false): Promise<void> {
    if (fresh) {
        document.querySelectorAll(`script[data-src="${src}"]`).forEach((node) => node.remove());
        scripts.delete(src);
    }

    if (!scripts.has(src)) {
        scripts.set(
            src,
            new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = fresh ? `${src}?t=${Date.now()}` : src;
                script.dataset.src = src;
                script.async = true;
                script.onload = () => resolve();
                script.onerror = () => {
                    scripts.delete(src);
                    reject(new Error(t('payment.script_failed')));
                };
                document.head.appendChild(script);
            }),
        );
    }

    return scripts.get(src)!;
}

/* eslint-disable @typescript-eslint/no-explicit-any */
declare global {
    interface Window {
        Stripe?: any;
        Square?: any;
        authorizeNetAimResponseHandler?: (response: any) => void;
    }
}

function stripeGateway(client: PaymentClient): CardGateway {
    let stripe: any = null;
    let elements: any = null;
    let element: any = null;

    return {
        async mount(container) {
            if (!client.clientSecret) {
                throw new Error(t('payment.card_unavailable'));
            }
            await loadScript('https://js.stripe.com/basil/stripe.js');
            stripe = window.Stripe(client.publishableKey, client.options ?? {});
            stripe.registerAppInfo?.({
                name: 'TastyIgniter Stripe',
                partner_id: 'pp_partner_JZyCCGR3cOwj9S',
                url: 'https://tastyigniter.com/marketplace/item/igniter-payregister',
            });
            elements = stripe.elements({
                clientSecret: client.clientSecret,
                appearance: {
                    theme: 'night',
                    variables: {
                        colorPrimary: '#F4A261',
                        colorBackground: '#221828',
                        colorText: '#ffffff',
                        borderRadius: '14px',
                        fontFamily: '"Plus Jakarta Sans", system-ui, sans-serif',
                    },
                },
                fonts: [
                    { cssSrc: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600&display=swap' },
                ],
            });
            element = elements.create('payment', { layout: { type: 'tabs' } });
            element.mount(container);
        },
        async check() {
            const { error } = await elements.submit();
            if (error) {
                throw new Error(error.message);
            }
        },
        async collect() {
            // The order total may have changed while saving; refresh the intent amount.
            await elements.fetchUpdates();
            const result = await stripe.confirmPayment({
                elements,
                redirect: 'if_required',
                confirmParams: { return_url: window.location.href },
            });
            const status = result.error?.payment_intent?.status ?? result.paymentIntent?.status;
            if (result.error && !['requires_capture', 'succeeded'].includes(status)) {
                throw new Error(result.error.message ?? t('payment.charge_failed'));
            }

            return {
                ...client.hidden,
                stripe_payment_method: String(result.paymentIntent?.payment_method ?? ''),
            };
        },
        destroy() {
            element?.destroy();
        },
    };
}

function squareGateway(client: PaymentClient): CardGateway {
    let payments: any = null;
    let card: any = null;

    return {
        async mount(container) {
            await loadScript(client.sdkUrl!);
            payments = window.Square.payments(client.applicationId, client.locationId);
            // Square keeps its field container white, so style it as a light input for legibility.
            card = await payments.card({
                style: {
                    input: { backgroundColor: '#FFFFFF', color: '#1B1220', fontSize: '16px' },
                    'input::placeholder': { color: '#8C8291' },
                    '.input-container': { borderRadius: '14px' },
                    '.input-container.is-focus': { borderColor: '#F4A261' },
                    '.message-text': { color: '#C9C0CD' },
                    '.message-icon': { color: '#C9C0CD' },
                },
            });
            await card.attach(container);
        },
        async check() {},
        async collect(latest, buyer) {
            const result = await card.tokenize();
            if (result.status !== 'OK') {
                throw new Error(result.errors?.map((error: any) => error.message).join(' ') || t('payment.check_card'));
            }

            let verification = '';
            try {
                const verified = await payments.verifyBuyer(result.token, {
                    intent: 'CHARGE',
                    amount: latest.amount ?? client.amount,
                    currencyCode: latest.currency ?? client.currency,
                    billingContact: { givenName: buyer.firstName, familyName: buyer.lastName, email: buyer.email },
                });
                verification = verified?.token ?? '';
            } catch {
                // Buyer verification is optional for many sellers; the nonce alone still charges.
            }

            return { ...client.hidden, square_card_nonce: result.token, square_card_token: verification };
        },
        destroy() {
            card?.destroy();
        },
    };
}

function authorizeNetGateway(client: PaymentClient): CardGateway {
    let button: HTMLButtonElement | null = null;
    let pending: { resolve: (value: any) => void; reject: (error: Error) => void } | null = null;
    let opaqueData: { dataDescriptor: string; dataValue: string } | null = null;
    let watch: number | null = null;

    // Opens Authorize.Net's hosted card form and waits for its response.
    function showForm(): Promise<any> {
        if (!button || !(window as any).AcceptUI) {
            return Promise.reject(new Error(t('payment.authorizenet_loading')));
        }

        return new Promise<any>((resolve, reject) => {
            pending = { resolve, reject };
            button?.click();

            // AcceptUI has no close callback; treat the popup closing without a response as a cancel.
            let opened = false;
            const startedAt = Date.now();
            watch = window.setInterval(() => {
                const shown = document.getElementById('AcceptUIContainer')?.classList.contains('show') ?? false;
                opened ||= shown;
                if (!pending) {
                    window.clearInterval(watch);
                } else if (opened && !shown) {
                    window.clearInterval(watch);
                    pending.reject(new Error(t('payment.card_cancelled')));
                    pending = null;
                } else if (!opened && Date.now() - startedAt > 4000) {
                    window.clearInterval(watch);
                    pending.reject(new Error(t('payment.authorizenet_blocked')));
                    pending = null;
                }
            }, 300);
        });
    }

    return {
        async mount(container) {
            button = document.createElement('button');
            button.type = 'button';
            button.className = 'AcceptUI';
            button.hidden = true;
            button.setAttribute('data-billingAddressOptions', '{"show":true, "required":false}');
            button.setAttribute('data-apiLoginID', client.apiLoginId ?? '');
            button.setAttribute('data-clientKey', client.clientKey ?? '');
            button.setAttribute('data-paymentOptions', '{"showCreditCard": true, "showBankAccount": false}');
            button.setAttribute('data-acceptUIFormHeaderTxt', t('payment.card_information'));
            button.setAttribute('data-responseHandler', 'authorizeNetAimResponseHandler');
            container.appendChild(button);

            window.authorizeNetAimResponseHandler = (response) => {
                if (response?.messages?.resultCode === 'Error') {
                    pending?.reject(new Error(response.messages.message.map((message: any) => message.text).join(' ')));
                } else {
                    pending?.resolve(response);
                }
                pending = null;
            };

            // AcceptUI binds to .AcceptUI buttons when it loads, so load it after the button exists.
            document.querySelectorAll('#AcceptUIContainer, #AcceptUIBackground').forEach((node) => node.remove());
            await loadScript(`${client.endpoint}/v1/Accept.js`);
            await loadScript(`${client.endpoint}/v3/AcceptUI.js`, true);
        },
        async check() {},
        async open() {
            const response = await showForm();
            opaqueData = response.opaqueData;
            const number = String(response.encryptedCardData?.cardNumber ?? '')
                .replace(/\D/g, '')
                .slice(-4);

            return number ? t('payment.card_ending', { number }) : t('payment.card_added');
        },
        async collect() {
            // Use the card already entered, otherwise open the form now (as the Orange theme does).
            const data = opaqueData ?? (await showForm()).opaqueData;

            return {
                ...client.hidden,
                authorizenetaim_DataDescriptor: data.dataDescriptor,
                authorizenetaim_DataValue: data.dataValue,
            };
        },
        destroy() {
            if (watch !== null) {
                window.clearInterval(watch);
                watch = null;
            }
            button?.remove();
            document.querySelectorAll('#AcceptUIContainer, #AcceptUIBackground').forEach((node) => node.remove());
            delete window.authorizeNetAimResponseHandler;
        },
    };
}

export function createGateway(client: PaymentClient): CardGateway {
    switch (client.type) {
        case 'stripe':
            return stripeGateway(client);
        case 'square':
            return squareGateway(client);
        case 'authorizenet':
            return authorizeNetGateway(client);
    }
}

/** POST JSON to a theme endpoint with Laravel's CSRF cookie. */
export async function postJson<T>(
    url: string,
    data: Record<string, unknown>,
): Promise<{ ok: boolean; status: number; body: T & { message?: string; errors?: Record<string, string[]> } }> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(data),
    });

    return { ok: response.ok, status: response.status, body: await response.json().catch(() => ({})) };
}
