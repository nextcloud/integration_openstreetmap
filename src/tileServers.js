import { generateUrl } from '@nextcloud/router'

import {
	getOpenFreeMapStyles as getDefaultOpenFreeMapStyles,
	getVectorStyles as getDefaultVectorStyles,
	getRasterTileServers as getDefaultRasterTileServers,
} from './map/tileServers.js'

/**
 * All the tile server styles available in this app,
 * proxied through the app backend if requested.
 *
 * @param {string|null} apiKey the MapTiler API key
 * @param {boolean} proxyMapRequests whether tile server requests should go through the app backend
 */
export function getStyles(apiKey, proxyMapRequests) {
	return {
		...getOpenFreeMapStyles(apiKey, proxyMapRequests),
		...(apiKey ? getVectorStyles(apiKey, proxyMapRequests) : {}),
		...getRasterTileServers(apiKey, proxyMapRequests),
	}
}

export function getOpenFreeMapStyles(apiKey, proxyMapRequests) {
	if (!proxyMapRequests) {
		return getDefaultOpenFreeMapStyles()
	}
	return {
		openfreemapLiberty: {
			title: 'OpenFreeMap Liberty',
			uri: generateUrl('/apps/integration_openstreetmap/openfreemap/styles/liberty'),
		},
	}
}

export function getVectorStyles(apiKey, proxyMapRequests) {
	if (!proxyMapRequests) {
		return getDefaultVectorStyles(apiKey)
	}
	return {
		streets: {
			title: 'Streets',
			maptiler: true,
			uri: generateUrl('/apps/integration_openstreetmap/maptiler/maps/streets-v2/style.json?key=' + apiKey),
		},
		satellite: {
			title: 'Satellite',
			maptiler: true,
			uri: generateUrl('/apps/integration_openstreetmap/maptiler/maps/hybrid/style.json?key=' + apiKey),
		},
		outdoor: {
			title: 'Outdoor',
			maptiler: true,
			uri: generateUrl('/apps/integration_openstreetmap/maptiler/maps/outdoor-v2/style.json?key=' + apiKey),
		},
		osm: {
			title: 'OpenStreetMap',
			maptiler: true,
			uri: generateUrl('/apps/integration_openstreetmap/maptiler/maps/openstreetmap/style.json?key=' + apiKey),
		},
		dark: {
			title: 'Dark',
			maptiler: true,
			uri: generateUrl('/apps/integration_openstreetmap/maptiler/maps/streets-dark/style.json?key=' + apiKey),
		},
	}
}

export function getRasterTileServers(apiKey, proxyMapRequests) {
	const styles = getDefaultRasterTileServers(apiKey)
	if (!proxyMapRequests) {
		return styles
	}
	// keep the default style structure, only replace the tile URLs
	styles.osmRaster.sources['osm-source'].tiles = [
		generateUrl('/apps/integration_openstreetmap/tiles/osm/') + '{x}/{y}/{z}',
	]
	styles.esriTopo.sources['esri-topo-source'].tiles = [
		generateUrl('/apps/integration_openstreetmap/tiles/esri-topo/') + '{x}/{y}/{z}',
	]
	styles.waterColor.sources['watercolor-source'].tiles = [
		generateUrl('/apps/integration_openstreetmap/tiles/watercolor/') + '{x}/{y}/{z}',
	]
	return styles
}

/**
 * @param {string|null} apiKey the MapTiler API key
 * @param {boolean} proxyMapRequests whether terrain requests should go through the app backend
 */
export function getTerrainTilesUrl(apiKey, proxyMapRequests) {
	if (!proxyMapRequests) {
		return null
	}
	return generateUrl('/apps/integration_openstreetmap/maptiler/tiles/terrain-rgb-v2/tiles.json?key=' + apiKey)
}

/**
 * @param {boolean} proxyMapRequests whether the MapTiler logo should go through the app backend
 */
export function getMaptilerLogoUrl(proxyMapRequests) {
	return proxyMapRequests
		? generateUrl('/apps/integration_openstreetmap/maptiler/resources/logo.svg')
		: 'https://api.maptiler.com/resources/logo.svg'
}