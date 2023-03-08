<template>
	<div class="point-picker-content">
		<h2>
			{{ t('integration_openstreetmap', 'Map location (by OpenStreetMap)') }}
		</h2>
		<!--a v-if="currentLink"
			:href="currentLink"
			target="_blank"
			class="preview-link">
			{{ currentLink }}
		</a>
		<span v-else>
			…
		</span-->
		<NcSearch ref="url-input"
			class="generic-search"
			:provider="provider"
			:show-empty-content="false"
			:search-placeholder="searchPlaceholder"
			@submit="onSearchSubmit" />
		<MaplibreMap v-if="showMap"
			class="point-map"
			:center="lastCenter"
			:zoom="lastMapState?.zoom"
			:pitch="lastMapState?.pitch"
			:bearing="lastMapState?.bearing"
			:map-style="lastMapState?.mapStyle"
			:marker="currentMarker"
			:all-move-events="true"
			@map-state-change="onMapStateChange" />
		<div class="footer">
			<NcCheckboxRadioSwitch
				class="marker-checkbox"
				:checked.sync="includeMarker">
				{{ t('integration_openstreetmap', 'Include marker') }}
			</NcCheckboxRadioSwitch>
			<div class="spacer" />
			<NcButton
				class="submit-button"
				type="primary"
				@click="onMapSubmit">
				{{ t('integration_openstreetmap', 'Generate point link') }}
				<template #icon>
					<ArrowRightIcon />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MaplibreMap from '../components/map/MaplibreMap.vue'

import { getProvider, NcSearch } from '@nextcloud/vue/dist/Components/NcRichText.js'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'

export default {
	name: 'PointCustomPickerElement',

	components: {
		MaplibreMap,
		NcButton,
		NcSearch,
		NcCheckboxRadioSwitch,
		ArrowRightIcon,
	},

	props: {
		providerId: {
			type: String,
			required: true,
		},
		accessible: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			provider: getProvider(this.providerId),
			currentCenter: null,
			currentZoom: null,
			currentPitch: null,
			currentBearing: null,
			currentMapStyle: null,
			showMap: false,
			lastMapState: getLastMapState(),
			searchPlaceholder: t('integration_openstreetmap', 'Search with Nominatim to get an OpenStreetMap link'),
			includeMarker: true,
		}
	},

	computed: {
		lastCenter() {
			if (this.lastMapState === null) {
				return null
			}
			return {
				lat: this.lastMapState.lat,
				lon: this.lastMapState.lon,
			}
		},
		lastZoom() {
			if (this.lastMapState === null) {
				return null
			}
			return this.lastMapState.zoom
		},
		currentMarker() {
			return this.includeMarker
				? this.currentCenter
					? this.currentCenter
					: this.lastCenter
				: undefined
		},
		currentLink() {
			if (this.currentCenter === null) {
				return null
			}
			const lat = this.currentCenter.lat
			const lon = this.currentCenter.lon
			const zoom = this.currentZoom
			if (this.includeMarker) {
				return 'https://www.openstreetmap.org/'
					+ '?mlat=' + lat
					+ '&mlon=' + lon
					+ '#map=' + zoom + '/' + lat + '/' + lon
			} else {
				return 'https://www.openstreetmap.org/#map=' + zoom + '/' + lat + '/' + lon
			}
		},
	},

	watch: {
	},

	mounted() {
		this.$nextTick(() => {
			this.showMap = true
		})
		console.debug('my provider is ', this.provider)
	},

	methods: {
		onMapSubmit() {
			const lat = this.currentCenter.lat
			const lon = this.currentCenter.lon
			const zoom = this.currentZoom
			const pitch = this.currentPitch
			const bearing = this.currentBearing
			const mapStyle = this.currentMapStyle
			setLastMapState(lat, lon, zoom, pitch, bearing, mapStyle)
			this.$emit('submit', this.currentLink)
		},
		onSearchSubmit(link) {
			this.$emit('submit', link)
		},
		onMapStateChange(e) {
			if (e.centerLat && e.centerLng) {
				this.currentCenter = {
					lat: parseFloat(e.centerLat.toFixed(6)),
					lon: parseFloat(e.centerLng.toFixed(6)),
				}
			}
			if (e.zoom) {
				this.currentZoom = Math.floor(e.zoom)
			}
			if (e.pitch) {
				this.currentPitch = e.pitch
			}
			if (e.bearing) {
				this.currentBearing = e.bearing
			}
			if (e.mapStyle) {
				this.currentMapStyle = e.mapStyle
			}
		},
	},
}
</script>

<style scoped lang="scss">
.point-picker-content {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;

	h2 {
		display: flex;
		align-items: center;
	}

	.generic-search {
		margin-bottom: 12px;
		width: 100%;
	}

	.point-map {
		width: 100%;
		height: 2000px;

		::v-deep .maplibregl-map {
			border-radius: var(--border-radius-large);
		}
	}

	.preview-link {
		margin-bottom: 8px;
	}

	.spacer {
		flex-grow: 1;
	}

	.footer {
		width: 100%;
		display: flex;
		margin-top: 8px;
		align-items: center;

		.marker-checkbox {
			margin-left: 16px;
		}
	}
}
</style>
