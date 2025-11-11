import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { linkTypes, routingLinkTypes } from './mapUtils.js'

if (!window._osm_last_map_state) {
	window._osm_last_map_state = loadState('integration_openstreetmap', 'last-map-state', {})
}

export function getLastMapState() {
	return {
		lat: 20,
		lon: -40,
		zoom: 2,
		pitch: 0,
		bearing: 0,
		mapStyle: undefined,
		terrain: false,
		globe: false,
		linkType: linkTypes.osm.id,
		routingLinkType: routingLinkTypes.osrm_org.id,
		...window._osm_last_map_state,
	}
}

export function setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, globe, linkType, routingLinkType }) {
	const state = { lat, lon, zoom, pitch, bearing, mapStyle, terrain, globe, linkType, routingLinkType }
	Object.keys(state).forEach(k => {
		if (state[k] !== undefined) {
			window._osm_last_map_state[k] = state[k]
		}
	})
	const req = {
		values: { lat, lon, zoom, pitch, bearing, mapStyle, terrain, globe, linkType, routingLinkType },
	}
	const url = generateUrl('/apps/integration_openstreetmap/config')
	axios.put(url, req).then((response) => {
		console.debug(response.data)
	}).catch((error) => {
		console.error(error)
	})
}
