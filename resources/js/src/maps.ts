import {t} from './lang';

export interface MapPoint {
    lat: number;
    lng: number;
}

export interface MapSession {
    /** Which basemap actually loaded. */
    provider: 'nominatim' | 'google';
    destroy(): void;
}

interface LeafletLatLng {
    lat: number;
    lng: number;
}

interface LeafletMap {
    setView(center: MapPoint, zoom: number): void;
    getCenter(): LeafletLatLng;
    panTo(center: MapPoint): void;
    on(event: 'click', handler: (event: { latlng: LeafletLatLng }) => void): void;
    on(event: 'move' | 'moveend', handler: () => void): void;
    invalidateSize(): void;
    remove(): void;
}

interface LeafletMarker {
    setLatLng(position: MapPoint | LeafletLatLng): void;
    addTo(map: LeafletMap): LeafletMarker;
}

interface LeafletLibrary {
    map(element: HTMLElement): LeafletMap;
    tileLayer(url: string, options: { maxZoom: number; attribution: string }): { addTo(map: LeafletMap): void };
    marker(position: MapPoint): LeafletMarker;
    Icon: {
        Default: {
            prototype: { _getIconUrl?: unknown };
            mergeOptions(options: { iconRetinaUrl: string; iconUrl: string; shadowUrl: string }): void;
        };
    };
}

interface GoogleLatLng {
    lat(): number;
    lng(): number;
}

interface GoogleMap {
    getCenter(): GoogleLatLng | null | undefined;
    panTo(position: MapPoint): void;
    addListener(event: string, handler: (event?: { latLng?: GoogleLatLng | null }) => void): void;
}

interface GoogleMarker {
    setPosition(position: MapPoint): void;
}

interface GoogleMaps {
    Map: new (
        element: HTMLElement,
        options: {
            zoom: number;
            center: MapPoint;
            disableDefaultUI: boolean;
            clickableIcons: boolean;
            gestureHandling: 'greedy';
        },
    ) => GoogleMap;
    Marker: new (options: { map: GoogleMap; position: MapPoint; title: string }) => GoogleMarker;
}

declare global {
    interface Window {
        L?: LeafletLibrary;
        google?: { maps: GoogleMaps };
    }
}

const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

let leafletLoader: Promise<void> | null = null;
let googleLoader: Promise<void> | null = null;

function loadStyle(href: string): Promise<void> {
    if (document.querySelector(`link[href="${href}"]`)) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.onload = () => resolve();
        link.onerror = () => reject(new Error(t('location_pin.map_failed')));
        document.head.append(link);
    });
}

function loadScript(src: string): Promise<void> {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing?.getAttribute('data-loaded') === '1') {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const script = existing instanceof HTMLScriptElement ? existing : document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => {
            script.setAttribute('data-loaded', '1');
            resolve();
        };
        script.onerror = () => reject(new Error(t('location_pin.map_failed')));
        if (!existing) {
            document.head.append(script);
        }
    });
}

function loadLeaflet(): Promise<void> {
    if (window.L) {
        return Promise.resolve();
    }

    leafletLoader ??= Promise.all([loadStyle(LEAFLET_CSS), loadScript(LEAFLET_JS)])
        .then(() => undefined)
        .catch((error: unknown) => {
            leafletLoader = null;
            throw error;
        });

    return leafletLoader;
}

function loadGoogleMaps(key: string): Promise<void> {
    if (window.google?.maps?.Map) {
        return Promise.resolve();
    }

    googleLoader ??= new Promise((resolve, reject) => {
        const callback = '__glassGoogleMapsLoaded';
        const timeout = window.setTimeout(() => {
            googleLoader = null;
            reject(new Error(t('location_pin.google_failed')));
        }, 12000);
        (window as unknown as Record<string, () => void>)[callback] = (): void => {
            window.clearTimeout(timeout);
            resolve();
        };
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?${new URLSearchParams({ key, v: 'weekly', callback })}`;
        script.async = true;
        script.onerror = () => {
            window.clearTimeout(timeout);
            googleLoader = null;
            reject(new Error(t('location_pin.google_failed')));
        };
        document.head.append(script);
    });

    return googleLoader;
}

function pointFromGoogle(latLng: GoogleLatLng): MapPoint {
    return { lat: latLng.lat(), lng: latLng.lng() };
}

/** OpenStreetMap pin. Dragging the map keeps the pin in the centre, same as the orange theme. */
async function mountOpenStreetMap(
    element: HTMLElement,
    position: MapPoint,
    onMove: (point: MapPoint) => void,
): Promise<MapSession> {
    await loadLeaflet();
    const leaflet = window.L;
    if (!leaflet) {
        throw new Error(t('location_pin.map_failed'));
    }

    delete leaflet.Icon.Default.prototype._getIconUrl;
    leaflet.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    const map = leaflet.map(element);
    map.setView(position, 15);
    leaflet
        .tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        })
        .addTo(map);
    const marker = leaflet.marker(position).addTo(map);

    // Keep the pin at the centre while the map moves (including the glide after a drag),
    // and look the spot up once the map has settled.
    map.on('move', () => marker.setLatLng(map.getCenter()));
    map.on('moveend', () => {
        const center = map.getCenter();
        onMove({ lat: center.lat, lng: center.lng });
    });
    map.on('click', (event) => map.panTo({ lat: event.latlng.lat, lng: event.latlng.lng }));

    const resize = window.setTimeout(() => map.invalidateSize(), 350);

    return {
        provider: 'nominatim',
        destroy() {
            window.clearTimeout(resize);
            map.remove();
            element.replaceChildren();
        },
    };
}

async function mountGoogleMap(
    element: HTMLElement,
    position: MapPoint,
    key: string,
    onMove: (point: MapPoint) => void,
): Promise<MapSession> {
    await loadGoogleMaps(key);
    const maps = window.google?.maps;
    if (!maps) {
        throw new Error(t('location_pin.google_failed'));
    }

    const map = new maps.Map(element, {
        zoom: 15,
        center: position,
        disableDefaultUI: true,
        clickableIcons: false,
        gestureHandling: 'greedy',
    });
    const marker = new maps.Marker({ map, position, title: t('location_pin.title') });

    // Same as OpenStreetMap: pin follows the centre; look up once the map is idle.
    let settled = false;
    map.addListener('center_changed', () => {
        const center = map.getCenter();
        if (center) {
            marker.setPosition(pointFromGoogle(center));
        }
    });
    map.addListener('idle', () => {
        const center = map.getCenter();
        // The first idle is the initial render, which the picker already looks up.
        if (center && settled) {
            onMove(pointFromGoogle(center));
        }
        settled = true;
    });
    map.addListener('click', (event) => {
        if (event?.latLng) {
            map.panTo(pointFromGoogle(event.latLng));
        }
    });

    return {
        provider: 'google',
        destroy() {
            element.replaceChildren();
        },
    };
}

/**
 * Nominatim uses OpenStreetMap. Google and chain use Google Maps when an API key is set,
 * matching the orange theme, and fall back to OpenStreetMap when the map cannot load.
 */
export async function mountDeliveryMap(
    element: HTMLElement,
    position: MapPoint,
    geocoder: string,
    key: string,
    onMove: (point: MapPoint) => void,
): Promise<MapSession> {
    if (geocoder !== 'nominatim' && key !== '') {
        try {
            return await mountGoogleMap(element, position, key, onMove);
        } catch {
            element.replaceChildren();
        }
    }

    return mountOpenStreetMap(element, position, onMove);
}
