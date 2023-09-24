import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

if (!window._osm_last_map_state) {
	window._osm_last_map_state = loadState('integration_openstreetmap', 'last-map-state', null)
}

export function getLastMapState() {
	return window._osm_last_map_state
}

export function setLastMapState(lat, lon, zoom, pitch, bearing, mapStyle, terrain, linkType) {
	window._osm_last_map_state = {
		lat,
		lon,
		zoom,
		pitch,
		bearing,
		mapStyle,
		terrain,
		linkType,
	}
	const req = {
		values: window._osm_last_map_state,
	}
	const url = generateUrl('/apps/integration_openstreetmap/config')
	axios.put(url, req).then((response) => {
		console.debug(response.data)
	}).catch((error) => {
		console.error(error)
	})
}
