export interface LocationCard {
    id: number;
    name: string;
    slug: string;
    address: string;
    offersDelivery: boolean;
    offersCollection: boolean;
    offersDineIn: boolean;
    distance?: number | null;
    distanceLabel?: string | null;
    hours: string;
    telephone?: string | null;
    /** Thumbnail URL, when the location has one. */
    image?: string | null;
    email?: string | null;
    week?: Array<{ day: string; hours: string }>;
    /** Average review score out of 5 and the number of approved reviews; null when reviews are off. */
    rating?: { score: number; count: number } | null;
    /** Minutes until the soonest order, keyed by order type (delivery, collection). */
    leadTimes?: Partial<Record<'delivery' | 'collection', number>>;
}

export interface CartLine {
    rowId: string;
    menuId: number;
    name: string;
    /** Dish thumbnail, when the dish has a photo. */
    image: string | null;
    qty: number;
    comment: string;
    /** Dine-in course: now, with the mains, or held. */
    course?: string;
    price: string;
    subtotal: string;
    options: Array<{ name: string; values: string[] }>;
    selected: Record<number, Array<{ id: number; qty: number }>> | [];
}

export interface SavedAddress {
    id: number;
    label: string;
    address1: string;
    address2: string | null;
    city: string;
    state: string | null;
    postcode: string;
    countryId: number;
}

export interface Fulfilment {
    orderType: string;
    orderTypeLabel: string;
    isAsap: boolean;
    date: string | null;
    time: string | null;
    timeLabel: string;
    address: string;
    addressId: number | null;
    coverage: null | { status: 'covered' | 'outside'; area: string | null; conditions: string[]; collection: boolean };
}

export interface DineInSeat {
    x: number;
    y: number;
    rotation: number;
    shape: string;
}

export interface DineInState {
    areas: Array<{ id: number; name: string; floorPlan: { stage?: { x: number; y: number; scaleX: number; scaleY: number } } | null }>;
    tables: Array<{
        id: number;
        areaId: number;
        name: string;
        guests: string;
        min: number;
        max: number;
        available: boolean;
        seat: DineInSeat;
    }>;
    areaId: number | null;
    tableId: number | null;
    seated: boolean;
    locked: boolean;
    guestCount: number | null;
    payLater: boolean;
    kitchenUrl: string | null;
    requestUrl: string | null;
    courseUrl: string | null;
    requests: string[];
    payable: boolean;
    billTotal: string | null;
    payOpenBill: boolean;
    payUrl: string | null;
    checkoutUrl: string | null;
}

export interface LoyaltyState {
    available: number;
    minimum: number;
    applied: number;
    canApply: boolean;
    cartEmpty: boolean;
    signedIn: boolean;
}

export interface FulfilmentOption {
    code: string;
    label: string;
    asap: boolean;
    days: Array<{ date: string; label: string; times: string[] }>;
}

export interface CartTotal {
    code: string;
    title: string;
    value: number;
    label: string;
}

export interface GroupOrderGuest {
    id: number;
    name: string;
    status: 'joined' | 'submitted' | 'left';
    statusLabel: string;
    items: Array<{ name: string; qty: number; price: string }>;
    subtotal: string | null;
    canRemove: boolean;
}

export interface GroupOrderSession {
    id: number;
    hash: string;
    isHost: boolean;
    isGuest: boolean;
    hostName: string;
    title: string;
    bannerTitle: string;
    description: string;
    location: string;
    payment: string;
    deliveryAddress: string | null;
    locked: boolean;
    deadline: { set: boolean; passed: boolean; remaining: string | null };
    inviteUrl: string;
    guestCount: number;
    canLock: boolean;
    guests: GroupOrderGuest[];
    totals: { guests: string; contribution: string | null } | null;
}

export interface GroupOrderState {
    canStart: boolean;
    session: GroupOrderSession | null;
}

export interface StorefrontShared {
    siteName: string;
    currency: string;
    customer: null | {
        id: number;
        firstName: string;
        lastName: string;
        email: string;
        telephone: string | null;
        addresses: SavedAddress[];
    };
    orderType: 'delivery' | 'collection' | string;
    postcode: string;
    fulfilment: Fulfilment;
    location: LocationCard | null;
    locations: LocationCard[];
    dineIn: DineInState | null;
    groupOrder?: GroupOrderState | null;
    loyalty: LoyaltyState | null;
    maps: { geocoder: string; key: string };
    cart: {
        count: number;
        lines: CartLine[];
        totals: CartTotal[];
        coupon: string;
        couponApplied: boolean;
        giftup: string;
        giftupApplied: boolean;
        tip: null | {
            percentage: boolean;
            presets: Array<{ value: number; label: string }>;
            amount: number;
            isCustom: boolean;
        };
        checkout: { blocked: boolean; reason: string | null; minimum: string | null };
        amount: number;
        total: string;
        subtotal: string;
    };
}

export interface MenuOption {
    id: number;
    name: string;
    displayType: string;
    required: boolean;
    minSelected: number;
    maxSelected: number;
    values: Array<{ id: number; name: string; price: number; priceLabel: string; allergens: string[] }>;
}

export interface MenuItem {
    id: number;
    name: string;
    description: string;
    ingredients: string[];
    allergens: string[];
    price: number;
    priceLabel: string;
    minimumQty: number;
    image: string | null;
    outOfStock: boolean;
    available: boolean;
    mealtimes: Array<{ name: string; description: string }>;
    categoryId: number;
    options: MenuOption[];
}

export interface MenuGroup {
    id: number;
    name: string;
    slug: string;
    priority: number;
    /** The category's own image, when it has one. */
    image: string | null;
    items: MenuItem[];
}

export interface MenuLink {
    title: string;
    url: string | null;
    items: MenuLink[];
}

export interface SiteShared {
    mainMenu: MenuLink[];
    footerMenu: MenuLink[];
    social: Array<{ network: string; url: string }>;
    cookieBanner: null | { message: string; acceptText: string; moreInfoText: string; moreInfoUrl: string | null };
    captcha: null | { siteKey: string; lang: string };
    brand: { logo: string | null; favicon: string | null; showName: boolean };
    themeColor: string;
    pwaIcon: string | null;
    /** Customize > General image switches. */
    display: { menuImages: boolean; categoryImages: boolean };
    /** Customize > General: suggest addresses while the customer types. */
    addressSuggestions: boolean;
}

export interface HomeContent {
    heroTitle: string;
    heroText: string;
    slides: Array<{ url: string; title: string; description: string }>;
    slideInterval: number;
    banners: Array<{
        code: string;
        type: 'image' | 'custom';
        url: string | null;
        alt: string;
        images: string[];
        markup: string | null;
    }>;
    featuredTitle: string;
    featured: MenuItem[];
    /** Default location menu, when the homepage search is replaced by a button. */
    orderButton?: string | null;
}

export interface CheckoutField {
    name: string;
    label: string;
    type: string;
    tab: string;
    value: unknown;
    placeholder: string | null;
    comment: string | null;
    required: boolean;
    options: Array<{ value: string | number; label: string }>;
    attributes: Record<string, string>;
    termsUrl: string | null;
}

export interface OrderDetail {
    hash: string;
    id: number;
    total: string;
    type: string;
    date: string;
    time: string;
    placed: { date: string; time: string };
    status: string;
    /** The admin's comment for the current status, when the customer was notified of it. */
    statusComment: string | null;
    /** 1 received, 2 preparing, 3 delivered or collected; null when cancelled. */
    progress: 1 | 2 | 3 | null;
    payment: string | null;
    deliveryAddress: string | null;
    location: string | null;
    comment: string | null;
    deliveryComment: string | null;
    menus: Array<{
        name: string;
        qty: number;
        price: string;
        comment: string | null;
        options: Array<{ name: string; values: string[] }>;
    }>;
    totals: Array<{ title: string; label: string }>;
    canCancel: boolean;
    confirmEmail?: boolean;
    canReorder: boolean;
    isCanceled: boolean;
    table?: { name: string; area: string | null; guests: string } | null;
    tab?: { code: string; label: string } | null;
}

/** Page links for a storefront list; hidden while everything fits on one page. */
export interface Pagination {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number;
    to: number;
}

export interface OrderContact {
    name: string;
    email: string;
    telephone: string;
}

export interface OrderRestaurant {
    name: string;
    address: string;
    telephone: string;
    orderUrl: string;
}

export interface ReviewState {
    type: 'order' | 'reservation';
    hash: string;
    signedIn: boolean;
    completed: boolean;
    hints: string[];
    existing: null | { quality: number; delivery: number; service: number; comment: string; approved: boolean };
}

/** A page of a location's approved reviews, newest first. */
export interface LocationReviews {
    total: number;
    averages: { quality: number | null; delivery: number | null; service: number | null };
    reviews: Array<{
        id: number;
        author: string;
        rating: number;
        quality: number;
        delivery: number;
        service: number;
        comment: string;
        date: string;
    }>;
    nextPage: number | null;
}
