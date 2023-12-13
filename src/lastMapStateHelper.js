import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

if (!window._osm_last_map_state) {
	window._osm_last_map_state = loadState('integration_openstreetmap', 'last-map-state', {})
}

export function getLastMapState() {
	return window._osm_last_map_state
}

export function setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, linkType }) {
	const state = { lat, lon, zoom, pitch, bearing, mapStyle, terrain, linkType }
	Object.keys(state).forEach(k => {
		if (state[k] !== undefined) {
			window._osm_last_map_state[k] = state[k]
		}
	})
	const req = {
		values: { lat, lon, zoom, pitch, bearing, mapStyle, terrain, linkType },
	}
	const url = generateUrl('/apps/integration_openstreetmap/config')
	axios.put(url, req).then((response) => {
		console.debug(response.data)
	}).catch((error) => {
		console.error(error)
	})
}
