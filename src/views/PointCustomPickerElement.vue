<template>
	<div class="point-picker-content">
		<h2>
			{{ t('integration_openstreetmap', 'Map location (by OpenStreetMap)') }}
		</h2>
		<a v-if="currentLink"
			:href="currentLink"
			target="_blank"
			class="preview-link">
			{{ currentLink }}
		</a>
		<span v-else>
			…
		</span>
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
		<NcButton
			class="submit-button"
			type="primary"
			@click="onSubmit">
			{{ t('integration_openstreetmap', 'Submit') }}
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import MaplibreMap from '../components/map/MaplibreMap.vue'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'

export default {
	name: 'PointCustomPickerElement',

	components: {
		MaplibreMap,
		NcButton,
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
			currentCenter: null,
			currentZoom: null,
			currentPitch: null,
			currentBearing: null,
			currentMapStyle: null,
			showMap: false,
			lastMapState: getLastMapState(),
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
			return this.currentCenter ? this.currentCenter : undefined
		},
		currentLink() {
			if (this.currentCenter === null) {
				return null
			}
			const lat = this.currentCenter.lat
			const lon = this.currentCenter.lon
			const zoom = this.currentZoom
			return 'https://www.openstreetmap.org/'
				+ '?mlat=' + lat
				+ '&mlon=' + lon
				+ '#map=' + zoom + '/' + lat + '/' + lon
		},
	},

	watch: {
	},

	mounted() {
		this.$nextTick(() => {
			this.showMap = true
		})
	},

	methods: {
		onSubmit() {
			const lat = this.currentCenter.lat
			const lon = this.currentCenter.lon
			const zoom = this.currentZoom
			const pitch = this.currentPitch
			const bearing = this.currentBearing
			const mapStyle = this.currentMapStyle
			setLastMapState(lat, lon, zoom, pitch, bearing, mapStyle)
			this.$emit('submit', this.currentLink)
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

	.point-map {
		width: 100%;
		height: 2000px;
	}

	.preview-link {
		margin-bottom: 8px;
	}

	.submit-button {
		margin-top: 8px;
		align-self: end;
	}
}
</style>
