import CarIcon from 'vue-material-design-icons/Car.vue'
import WalkIcon from 'vue-material-design-icons/Walk.vue'
import BicycleIcon from 'vue-material-design-icons/Bicycle.vue'

import { Map } from 'maplibre-gl'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const mapVectorImages = {
	marker: 'mapIcons/marker.svg',
	marker_red: 'mapIcons/marker-red.svg',
	marker_green: 'mapIcons/marker-green.svg',
}

export const linkTypes = {
	osm: {
		id: 'osm',
		label: t('integration_openstreetmap', 'OpenStreetMap'),
	},
	osmand: {
		id: 'osmand',
		label: t('integration_openstreetmap', 'OsmAnd'),
	},
	google: {
		id: 'google',
		label: t('integration_openstreetmap', 'Google maps'),
	},
}
export const linkTypesArray = Object.keys(linkTypes).map(typeId => linkTypes[typeId])

export const routingProfiles = {
	car: {
		id: 'routed-car',
		label: t('integration_openstreetmap', 'By car'),
		srv: 0,
		ghpProfile: 'car',
		osmandProfile: 'car',
		icon: CarIcon,
	},
	bike: {
		id: 'routed-bike',
		label: t('integration_openstreetmap', 'By bike'),
		srv: 1,
		ghpProfile: 'bike',
		osmandProfile: 'bicycle',
		icon: BicycleIcon,
	},
	foot: {
		id: 'routed-foot',
		label: t('integration_openstreetmap', 'By foot'),
		srv: 2,
		ghpProfile: 'foot',
		osmandProfile: 'pedestrian',
		icon: WalkIcon,
	},
}

export const routingLinkTypes = {
	osrm_osm_de: {
		id: 'osrm_osm_de',
		label: 'https://routing.openstreetmap.de',
	},
	osrm_org: {
		id: 'osrm_org',
		label: 'https://map.project-osrm.org',
	},
	graphhopper_com: {
		id: 'graphhopper_com',
		label: 'https://graphhopper.com/maps',
	},
	osmand_net: {
		id: 'osmand_net',
		label: 'https://osmand.net/map/navigate',
	},
}

export const routingLinkTypesArray = Object.values(routingLinkTypes)

export function shortenCoordinate(coordinate) {
	return parseFloat(coordinate.toFixed(6))
}

export function getRoutingLink(
	waypoints, profile = routingProfiles.car, linkTypeId = routingLinkTypes.osrm_org.id,
	terrain = false, globe = true, style = 'street',
) {
	if (waypoints === null || waypoints.length < 2) {
		return null
	}

	const selectedProfile = profile ?? routingProfiles.car

	let link
	const fragments = []
	const queryParams = []
	if (linkTypeId === null || linkTypeId === routingLinkTypes.osrm_org.id) {
		link = 'https://map.project-osrm.org/'
		queryParams.push(...waypoints.map(w => `loc=${shortenCoordinate(w[1])}%2C${shortenCoordinate(w[0])}`))
		queryParams.push('srv=' + selectedProfile.srv)
	} else if (linkTypeId === routingLinkTypes.osrm_osm_de.id) {
		link = 'https://routing.openstreetmap.de/'
		queryParams.push(...waypoints.map(w => `loc=${shortenCoordinate(w[1])}%2C${shortenCoordinate(w[0])}`))
		queryParams.push('srv=' + selectedProfile.srv)
	} else if (linkTypeId === routingLinkTypes.graphhopper_com.id) {
		link = 'https://graphhopper.com/maps/'
		queryParams.push(...waypoints.map(w => `point=${shortenCoordinate(w[1])}%2C${shortenCoordinate(w[0])}`))
		queryParams.push('profile=' + selectedProfile.ghpProfile)
	} else if (linkTypeId === routingLinkTypes.osmand_net.id) {
		link = 'https://osmand.net/map/navigate/'
		const startW = waypoints[0]
		const endW = waypoints[waypoints.length - 1]
		queryParams.push(`start=${shortenCoordinate(startW[1])},${shortenCoordinate(startW[0])}`)
		queryParams.push(`end=${shortenCoordinate(endW[1])},${shortenCoordinate(endW[0])}`)
		if (waypoints.length > 2) {
			const vias = waypoints.slice(1, waypoints.length - 1)
			queryParams.push('via=' + vias.map(v => `${shortenCoordinate(v[1])},${shortenCoordinate(v[0])}`).join(';'))
		}
		queryParams.push('profile=' + selectedProfile.osmandProfile)
		const centerLat = waypoints.map(w => w[1]).reduce((acc, e) => acc + e, 0) / waypoints.length
		const centerLon = waypoints.map(w => w[0]).reduce((acc, e) => acc + e, 0) / waypoints.length
		fragments.push(`14/${shortenCoordinate(centerLat)}/${shortenCoordinate(centerLon)}`)
	}

	if (terrain) {
		fragments.push('terrain')
	}
	if (globe) {
		fragments.push('globe')
	}
	if (style !== 'streets') {
		fragments.push('style=' + encodeURIComponent(style))
	}

	if (queryParams.length > 0) {
		link += '?' + queryParams.join('&')
	}
	if (fragments.length > 0) {
		link += '#' + fragments.join('&')
	}
	return link
}

export function getBBFromCenterZoom(lat, lon, zoom) {
	const dummyElement = document.createElement('div')
	const map = new Map({
		container: dummyElement,
		zoom,
	})
	const pixWidth = 600
	const pixHeight = 350
	const pixOffsetX = pixWidth / 2
	const pixOffsetY = pixHeight / 2
	// const pixOffsetY = pixOffsetX * 9 / 16

	const centerPoint = map.project([lon, lat])
	const ll1 = map.unproject([centerPoint.x - pixOffsetX, centerPoint.y + pixOffsetY])
	const ll2 = map.unproject([centerPoint.x + pixOffsetX, centerPoint.y - pixOffsetY])
	map.remove()
	return [ll1.lat, ll2.lat, ll1.lng, ll2.lng]
}

export async function nominatimGeocoder(query) {
	try {
		const req = {
			params: {
				query,
			},
		}
		const url = generateUrl('/apps/integration_openstreetmap/search')
		const result = await axios.get(url, req)
		const data = result.data.ocs.data
		console.debug('result', data)
		return formatNominatimToCarmentGeojson(data)
	} catch (error) {
		console.error('OSM search error', error)
	}
}

function formatNominatimToCarmentGeojson(results) {
	// https://docs.mapbox.com/api/search/geocoding/#geocoding-response-object
	return results.map(r => {
		const bb = r.boundingbox
		return {
			id: r.osm_id,
			place_name: r.display_name,
			bbox: [bb[2], bb[0], bb[3], bb[1]],
			// center: [r.lon, r.lat],
		}
	})
}

export async function maplibreForwardGeocode(config) {
	const features = []
	try {
		const req = {
			params: {
				q: config.query,
				// the controller fails saying "No responder registered for format geojson!"
				// format: 'geojson',
				rformat: 'geojson',
				polygon_geojson: 1,
				addressdetails: 1,
				limit: config.limit,
			},
		}
		// const url = 'https://nominatim.openstreetmap.org/search'
		const url = generateUrl('/apps/integration_openstreetmap/search')
		const response = await axios.get(url, req)
		const geojson = response.data.ocs.data
		for (const feature of geojson.features) {
			const center = [
				feature.bbox[0] + (feature.bbox[2] - feature.bbox[0]) / 2,
				feature.bbox[1] + (feature.bbox[3] - feature.bbox[1]) / 2,
			]
			const point = {
				type: 'Feature',
				geometry: {
					type: 'Point',
					coordinates: center,
				},
				place_name: feature.properties.display_name,
				properties: feature.properties,
				text: feature.properties.display_name,
				place_type: ['place'],
				center,
			}
			features.push(point)
		}
	} catch (e) {
		console.error(`Failed to forwardGeocode with error: ${e}`)
	}

	return {
		features,
	}
}
