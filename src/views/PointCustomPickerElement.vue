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
			:zoom="lastMapState?.zoom ?? undefined"
			:pitch="lastMapState?.pitch ?? undefined"
			:bearing="lastMapState?.bearing ?? undefined"
			:map-style="lastMapState?.mapStyle ?? undefined"
			:use-terrain="!!lastMapState?.terrain ?? undefined"
			:marker="currentMarker"
			:all-move-events="true"
			@map-state-change="onMapStateChange" />
		<div class="footer">
			<NcSelect
				:value="selectedLinkType"
				:options="linkTypesArray"
				:aria-label-combobox="t('integration_openstreetmap', 'Link type')"
				:placeholder="t('integration_openstreetmap', 'Link type')"
				input-id="extension-select"
				@input="onLinkTypeSelect" />
			<NcCheckboxRadioSwitch
				class="marker-checkbox"
				:checked.sync="includeMarker">
				{{ t('integration_openstreetmap', 'Include marker') }}
			</NcCheckboxRadioSwitch>
			<div class="spacer" />
			<NcButton
				class="submit-button"
				type="primary"
				:disabled="currentCenter === null"
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

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import MaplibreMap from '../components/map/MaplibreMap.vue'

import { getProvider, NcSearch } from '@nextcloud/vue/dist/Components/NcRichText.js'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'

const linkTypes = {
	osm: {
		id: 'osm',
		label: t('integration_openstreetmap', 'OpenStreetMap'),
	},
	osmand: {
		id: 'osmand',
		label: t('integration_openstreetmap', 'OsmAnd'),
	},
	google: {
		id: 'google',
		label: t('integration_openstreetmap', 'Google maps'),
	},
}
const linkTypesArray = Object.keys(linkTypes).map(typeId => linkTypes[typeId])

export default {
	name: 'PointCustomPickerElement',

	components: {
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
			currentCenter: null,
			currentZoom: null,
			currentPitch: getLastMapState()?.pitch ?? null,
			currentBearing: getLastMapState()?.bearing ?? null,
			currentMapStyle: getLastMapState()?.mapStyle ?? null,
			currentMapTerrain: !!getLastMapState()?.terrain,
			selectedLinkTypeId: getLastMapState()?.linkType ? getLastMapState().linkType : linkTypes.osm.id,
			showMap: false,
			lastMapState: getLastMapState(),
			searchPlaceholder: t('integration_openstreetmap', 'Search with Nominatim to get an OpenStreetMap link'),
			includeMarker: true,
			linkTypesArray,
		}
	},

	computed: {
		selectedLinkType() {
			return linkTypes[this.selectedLinkTypeId] ?? null
		},
		lastCenter() {
			if (this.lastMapState?.lat && this.lastMapState?.lon) {
				return {
					lat: this.lastMapState.lat,
					lon: this.lastMapState.lon,
				}
			}
			return null
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

			if (parseInt(this.currentPitch) !== 0) {
				fragments.push('pitch=' + parseInt(this.currentPitch))
			}
			if (parseInt(this.currentBearing) !== 0) {
				fragments.push('bearing=' + parseInt(this.currentBearing))
			}
			if (this.currentMapStyle !== 'streets') {
				fragments.push('style=' + encodeURIComponent(this.currentMapStyle))
			}
			if (this.currentMapTerrain) {
				fragments.push('terrain')
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
			const lat = this.currentCenter.lat
			const lon = this.currentCenter.lon
			const zoom = this.currentZoom
			const pitch = this.currentPitch
			const bearing = this.currentBearing ? parseFloat(this.currentBearing.toFixed(2)) : this.currentBearing
			const mapStyle = this.currentMapStyle
			const terrain = this.currentMapTerrain ? '1' : ''
			const linkType = this.selectedLinkTypeId
			setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, linkType })
			this.$emit('submit', this.currentLink)
		},
		onSearchSubmit(link) {
			setLastMapState({ mapStyle: this.currentMapStyle })
			const fragments = []
			fragments.push('style=' + encodeURIComponent(this.currentMapStyle))
			const finalLink = link + '#' + fragments.join('&')
			this.$emit('submit', finalLink)
		},
		onMapStateChange(e) {
			if (e.centerLat !== undefined && e.centerLng !== undefined) {
				this.currentCenter = {
					lat: parseFloat(e.centerLat.toFixed(6)),
					lon: parseFloat(e.centerLng.toFixed(6)),
				}
			}
			if (e.zoom !== undefined) {
				this.currentZoom = Math.floor(e.zoom)
			}
			if (e.pitch !== undefined) {
				this.currentPitch = e.pitch
			}
			if (e.bearing !== undefined) {
				this.currentBearing = e.bearing
			}
			if (e.mapStyle !== undefined) {
				this.currentMapStyle = e.mapStyle
			}
			if ([true, false].includes(e.terrain)) {
				this.currentMapTerrain = e.terrain
			}
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
		gap: 8px;

		.marker-checkbox {
			margin-left: 16px;
		}
	}
}
</style>
