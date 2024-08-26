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

export const routingProfiles = {
	car: {
		id: 'routed-car',
		label: t('integration_openstreetmap', 'By car'),
		srv: 0,
		ghpProfile: 'car',
		icon: CarIcon,
	},
	bike: {
		id: 'routed-bike',
		label: t('integration_openstreetmap', 'By bike'),
		srv: 1,
		ghpProfile: 'bike',
		icon: BicycleIcon,
	},
	foot: {
		id: 'routed-foot',
		label: t('integration_openstreetmap', 'By foot'),
		srv: 2,
		ghpProfile: 'foot',
		icon: WalkIcon,
	},
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
