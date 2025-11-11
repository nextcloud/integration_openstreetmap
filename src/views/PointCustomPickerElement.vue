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
			v-model:pitch="mapState.pitch"
			v-model:bearing="mapState.bearing"
			v-model:map-style="mapState.mapStyle"
			v-model:use-terrain="mapState.terrain"
			v-model:use-globe="mapState.globe"
			v-model:zoom="mapState.zoom"
			:center="mapCenter"
			:all-move-events="true"
			class="point-map"
			@update:center="onUpdateCenter">
			<template #default="{ map }">
				<VMarker v-if="currentMarker"
					:map="map"
					:lng-lat="currentMarker" />
			</template>
		</MaplibreMap>
		<div class="footer">
			<NcSelect
				class="type-select"
				:model-value="selectedLinkType"
				:options="linkTypesArray"
				:aria-label-combobox="t('integration_openstreetmap', 'Link type')"
				:placeholder="t('integration_openstreetmap', 'Link type')"
				input-id="extension-select"
				@update:model-value="onLinkTypeSelect" />
			<NcCheckboxRadioSwitch
				v-model="includeMarker"
				class="marker-checkbox">
				{{ t('integration_openstreetmap', 'Include marker') }}
			</NcCheckboxRadioSwitch>
			<div class="spacer" />
			<NcButton
				class="submit-button"
				variant="primary"
				:disabled="mapCenter === null"
				@click="onMapSubmit">
				{{ t('integration_openstreetmap', 'Generate location link') }}
				<template #icon>
					<ArrowRightIcon />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import MaplibreMap from '../components/map/MaplibreMap.vue'
import VMarker from '../components/map/VMarker.vue'

import { getProvider, NcSearch } from '@nextcloud/vue/components/NcRichText'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'
import { linkTypes, linkTypesArray } from '../mapUtils.js'

export default {
	name: 'PointCustomPickerElement',

	components: {
		VMarker,
		MaplibreMap,
		NcButton,
		NcSearch,
		NcSelect,
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
			selectedLinkTypeId: getLastMapState()?.linkType ? getLastMapState().linkType : linkTypes.osm.id,
			showMap: false,
			mapState: getLastMapState(),
			searchPlaceholder: t('integration_openstreetmap', 'Search with Nominatim to get an OpenStreetMap link'),
			includeMarker: true,
			linkTypesArray,
		}
	},

	computed: {
		selectedLinkType() {
			return linkTypes[this.selectedLinkTypeId] ?? null
		},
		mapCenter() {
			if (this.mapState?.lat && this.mapState?.lon) {
				return {
					lat: this.mapState.lat,
					lon: this.mapState.lon,
				}
			}
			return null
		},
		currentMarker() {
			return this.includeMarker
				? this.mapCenter
				: undefined
		},
		currentLink() {
			if (this.mapCenter === null) {
				return null
			}
			const lat = parseFloat(this.mapCenter.lat.toFixed(6))
			const lon = parseFloat(this.mapCenter.lon.toFixed(6))
			const zoom = Math.floor(this.mapState.zoom)

			let link
			const fragments = []
			const queryParams = []
			if (this.selectedLinkTypeId === null || this.selectedLinkTypeId === linkTypes.osm.id) {
				link = 'https://www.openstreetmap.org/'
				if (this.includeMarker) {
					queryParams.push('mlat=' + lat)
					queryParams.push('mlon=' + lon)
				}
				fragments.push('map=' + zoom + '/' + lat + '/' + lon)
			} else if (this.selectedLinkTypeId === linkTypes.osmand.id) {
				link = 'https://osmand.net/map/'
				if (this.includeMarker) {
					queryParams.push('pin=' + lat + ',' + lon)
				}
				fragments.push(zoom + '/' + lat + '/' + lon)
			} else if (this.selectedLinkTypeId === linkTypes.google.id) {
				link = 'https://maps.google.com/maps'
				if (this.includeMarker) {
					queryParams.push('q=' + lat + ',' + lon)
				}
				queryParams.push('ll=' + lat + ',' + lon)
				queryParams.push('z=' + zoom)
			}

			if (parseInt(this.mapState.pitch) !== 0) {
				fragments.push('pitch=' + parseInt(this.mapState.pitch))
			}
			if (parseInt(this.mapState.bearing) !== 0) {
				fragments.push('bearing=' + parseInt(this.mapState.bearing))
			}
			if (this.mapState.mapStyle !== 'streets') {
				fragments.push('style=' + encodeURIComponent(this.mapState.mapStyle))
			}
			if (this.mapState.terrain) {
				fragments.push('terrain')
			}
			if (this.mapState.globe) {
				fragments.push('globe')
			}

			if (queryParams.length > 0) {
				link += '?' + queryParams.join('&')
			}
			if (fragments.length > 0) {
				link += '#' + fragments.join('&')
			}

			return link
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
		onLinkTypeSelect(selected) {
			this.selectedLinkTypeId = selected?.id ?? null
		},
		onMapSubmit() {
			const lat = this.mapState.lat
			const lon = this.mapState.lon
			const zoom = this.mapState.zoom
			const pitch = this.mapState.pitch
			const bearing = this.mapState.bearing ? parseFloat(this.mapState.bearing.toFixed(2)) : this.mapState.bearing
			const mapStyle = this.mapState.mapStyle
			const terrain = this.mapState.terrain ? '1' : ''
			const globe = this.mapState.globe ? '1' : ''
			const linkType = this.selectedLinkTypeId
			setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, globe, linkType })
			this.$el.dispatchEvent(new CustomEvent('submit', { detail: this.currentLink, bubbles: true }))
		},
		onSearchSubmit(link) {
			setLastMapState({ mapStyle: this.mapState.mapStyle })
			const fragments = []
			fragments.push('style=' + encodeURIComponent(this.mapState.mapStyle))
			const finalLink = link + '#' + fragments.join('&')
			this.$el.dispatchEvent(new CustomEvent('submit', { detail: finalLink, bubbles: true }))
		},
		onUpdateCenter(center) {
			this.mapState.lat = center.lat
			this.mapState.lon = center.lng
		},
	},
}
</script>

<style lang="scss">
// TODO fix this in nc/vue
.modal-container__content .reference-picker-modal--content {
	height: 100%;
}
</style>

<style scoped lang="scss">
.point-picker-content {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 12px 16px 12px 16px;

	h2 {
		display: flex;
		align-items: center;
		margin-top: 0;
	}

	.generic-search {
		margin-bottom: 12px;
		padding: 0 !important;
		width: 100%;
	}

	.point-map {
		width: 100%;
		height: 2000px;

		:deep(.maplibregl-map) {
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
		gap: 8px;

		.marker-checkbox {
			margin-left: 16px;
		}

		.type-select {
			margin: 0;
		}
	}
}
</style>
