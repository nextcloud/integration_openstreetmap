<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<MaplibreMap
		:maptiler-api-key="maptilerApiKey"
		:styles="styles"
		:logo-url="logoUrl"
		:terrain-tiles-url="terrainTilesUrl"
		:geocoder-options="geocoderOptions">
		<template #default="{ map }">
			<slot :map="map" />
		</template>
	</MaplibreMap>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import MaplibreMap from '../map/MaplibreMap.vue'
import { getStyles, getTerrainTilesUrl, getMaptilerLogoUrl } from '../tileServers.js'
import { maplibreForwardGeocode } from '../mapUtils.js'

/**
 * App-specific wrapper around the generic MaplibreMap component.
 * It provides the app initial state (MapTiler API key, proxy setting)
 * and the proxied tile servers, logo, terrain and geocoder when proxying is enabled.
 * All other props, events and slots are forwarded to MaplibreMap.
 */
export default {
	name: 'AppMaplibreMap',

	components: {
		MaplibreMap,
	},

	props: {
	},

	data() {
		return {
			apiKeys: loadState('integration_openstreetmap', 'api-keys'),
			proxyMapRequests: loadState('integration_openstreetmap', 'proxy-map-requests', false),
		}
	},

	computed: {
		maptilerApiKey() {
			return this.apiKeys?.maptiler_api_key
		},
		styles() {
			return getStyles(this.maptilerApiKey, this.proxyMapRequests)
		},
		logoUrl() {
			return getMaptilerLogoUrl(this.proxyMapRequests)
		},
		terrainTilesUrl() {
			return getTerrainTilesUrl(this.maptilerApiKey, this.proxyMapRequests)
		},
		geocoderOptions() {
			return {
				placeholder: t('integration_openstreetmap', 'Search on the map'),
				// only proxy the search when requested,
				// otherwise the built-in direct Nominatim geocoder is used
				...(this.proxyMapRequests ? { forwardGeocode: maplibreForwardGeocode } : {}),
			}
		},
	},
}
</script>
