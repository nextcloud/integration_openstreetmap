<template>
	<div class="point-picker-content">
		<h2>
			{{ t('integration_openstreetmap', 'Map point picker') }}
		</h2>
		<a v-if="currentLink" :href="currentLink" target="_blank">
			{{ currentLink }}
		</a>
		<MaplibreMap v-if="showMap"
			class="point-map"
			:marker="currentMarker"
			:all-move-events="true"
			@map-state-change="onMapStateChange" />
		<NcButton type="primary" @click="onSubmit">
			{{ t('integration_openstreetmap', 'Submit') }}
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import MaplibreMap from '../components/map/MaplibreMap.vue'

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
			showMap: false,
		}
	},

	computed: {
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
			this.$emit('submit', this.currentLink)
		},
		onMapStateChange(e) {
			this.currentCenter = {
				lat: parseFloat(e.centerLat.toFixed(6)),
				lon: parseFloat(e.centerLng.toFixed(6)),
			}
			this.currentZoom = Math.floor(e.zoom)
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
	justify-content: center;
	//padding: 16px;

	h2 {
		display: flex;
		align-items: center;
	}

	.point-map {
		width: 100%;
		height: 350px;
	}
}
</style>
