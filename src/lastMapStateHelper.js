import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { linkTypes, routingLinkTypes } from './mapUtils.js'

if (!window._osm_last_map_state) {
	window._osm_last_map_state = loadState('integration_openstreetmap', 'last-map-state', {})
}

export function getLastMapState() {
	const state = {
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
	// the user config stores terrain/globe as '1' / '' strings and an empty
	// string would be cast to true by boolean props, so use real booleans
	state.terrain = toBoolean(state.terrain)
	state.globe = toBoolean(state.globe)
	return state
}

/**
 * @param {boolean|string|undefined} value the value to normalize
 * @return {boolean|undefined} true when enabled, undefined when value is undefined
 */
function toBoolean(value) {
	if (value === undefined) {
		return undefined
	}
	return value === true || value === '1'
}

/**
 * @param {boolean|undefined} value the boolean value to store
 * @return {string|undefined} '1' or '' for the user config storage, undefined when value is undefined
 */
function toStoredString(value) {
	if (value === undefined) {
		return undefined
	}
	return value ? '1' : ''
}

export function setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, globe, linkType, routingLinkType }) {
	// in public pages
	const currentUser = getCurrentUser()
	if (currentUser === null) {
		return
	}

	const state = {
		lat, lon, zoom, pitch, bearing, mapStyle, linkType, routingLinkType,
		terrain: toBoolean(terrain),
		globe: toBoolean(globe),
	}
	Object.keys(state).forEach(k => {
		if (state[k] !== undefined) {
			window._osm_last_map_state[k] = state[k]
		}
	})
	const req = {
		values: {
			lat, lon, zoom, pitch, bearing, mapStyle, linkType, routingLinkType,
			terrain: toStoredString(state.terrain),
			globe: toStoredString(state.globe),
		},
	}
	const url = generateUrl('/apps/integration_openstreetmap/config')
	axios.put(url, req).then((response) => {
		console.debug(response.data)
	}).catch((error) => {
		console.error(error)
	})
}
