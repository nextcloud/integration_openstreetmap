<template>
	<div class="direction-picker-content">
		<h2>
			{{ t('integration_openstreetmap', 'Map directions (by OSRM)') }}
		</h2>
		<div class="header">
			<RoutingProfilePicker v-model="selectedProfile"
				class="profile-select" />
			<div v-if="hint">
				{{ hint }}
			</div>
			<div v-if="formattedDistance && formattedDuration" class="route--info">
				<span :title="routeDistance">
					{{ t('integration_openstreetmap', 'Distance: {distance}', { distance: formattedDistance }) }}
				</span>
				|
				<span :title="routeDuration">
					{{ t('integration_openstreetmap', 'Duration: {duration}', { duration: formattedDuration }) }}
				</span>
			</div>
			<NcButton :title="helpText"
				variant="tertiary">
				<template #icon>
					<HelpCircleOutlineIcon />
				</template>
			</NcButton>
		</div>
		<!--a v-if="currentLink"
			:href="currentLink"
			target="_blank"
			class="preview-link">
			{{ currentLink }}
		</a>
		<span v-else>
			…
		</span-->
		<MaplibreMap v-if="showMap"
			v-model:pitch="mapState.pitch"
			v-model:bearing="mapState.bearing"
			v-model:map-style="mapState.mapStyle"
			v-model:use-terrain="mapState.terrain"
			v-model:use-globe="mapState.globe"
			v-model:zoom="mapState.zoom"
			:center="mapCenter"
			:all-move-events="true"
			class="direction-map"
			@update:center="onUpdateCenter">
			<template #default="{ map }">
				<DirectionsPlugin
					:map="map"
					:profile="selectedProfileId"
					@waypoint-change="onWaypointChange"
					@route-fetch="onRouteFetched" />
			</template>
		</MaplibreMap>
		<div class="footer">
			<label for="extension-select">
				{{ t('integration_openstreetmap', 'Link type') }}
			</label>
			<NcSelect
				class="type-select"
				:model-value="selectedLinkType"
				:options="routingLinkTypesArray"
				:aria-label-combobox="t('integration_openstreetmap', 'Link type')"
				:placeholder="t('integration_openstreetmap', 'Link type')"
				input-id="extension-select"
				@update:model-value="onLinkTypeSelect" />
			<div class="spacer" />
			<NcButton
				class="submit-button"
				variant="primary"
				:disabled="currentLink === null"
				@click="onMapSubmit">
				{{ t('integration_openstreetmap', 'Generate direction link') }}
				<template #icon>
					<ArrowRightIcon />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import HelpCircleOutlineIcon from 'vue-material-design-icons/HelpCircleOutline.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import MaplibreMap from '../components/map/MaplibreMap.vue'
import DirectionsPlugin from '../components/map/DirectionsPlugin.vue'
import RoutingProfilePicker from '../components/RoutingProfileSelect.vue'

import moment from '@nextcloud/moment'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'
import { routingProfiles, routingLinkTypes, routingLinkTypesArray, getRoutingLink } from '../mapUtils.js'

export default {
	name: 'DirectionCustomPickerElement',

	components: {
		RoutingProfilePicker,
		DirectionsPlugin,
		MaplibreMap,
		NcButton,
		NcSelect,
		ArrowRightIcon,
		HelpCircleOutlineIcon,
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
			selectedRoutingLinkTypeId: getLastMapState()?.routingLinkType ? getLastMapState().routingLinkType : routingLinkTypes.osrm_org.id,
			showMap: false,
			mapState: getLastMapState(),
			searchPlaceholder: t('integration_openstreetmap', 'Search with Nominatim to get an OpenStreetMap link'),
			routingLinkTypesArray,
			waypoints: null,
			routeDistance: null,
			routeDuration: null,
			selectedProfile: routingProfiles.car,
			helpText: t('integration_openstreetmap', 'Click on the map to add waypoints. Click on waypoints to delete them. Waypoints can be dragged.'),
		}
	},

	computed: {
		mapCenter() {
			if (this.mapState?.lat && this.mapState?.lon) {
				return {
					lat: this.mapState.lat,
					lon: this.mapState.lon,
				}
			}
			return null
		},
		profileList() {
			return Object.values(routingProfiles)
		},
		selectedProfileId() {
			if (this.selectedProfile) {
				return this.selectedProfile.id
			}
			return undefined
		},
		selectedLinkType() {
			return routingLinkTypes[this.selectedRoutingLinkTypeId] ?? null
		},
		hint() {
			if (this.waypoints === null || this.waypoints.length === 0) {
				return t('integration_openstreetmap', 'Click on the map to set the start location')
			} else if (this.waypoints.length === 1) {
				return t('integration_openstreetmap', 'Click on the map to add a waypoint')
			}
			return null
		},
		currentLink() {
			const link = getRoutingLink(
				this.waypoints, this.selectedProfile, this.selectedRoutingLinkTypeId,
				this.mapState.terrain, this.mapState.globe, this.mapState.mapStyle,
			)
			console.debug('[osm] current link', link)
			return link
		},
		formattedDuration() {
			if (this.waypoints === null || this.waypoints.length < 2 || this.routeDuration === null) {
				return null
			}
			return this.getFormattedDuration(this.routeDuration)
		},
		formattedDistance() {
			if (this.waypoints === null || this.waypoints.length < 2 || this.routeDistance === null) {
				return null
			}
			return this.getFormattedDistance(this.routeDistance)
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
		getFormattedDuration(duration) {
			const mDuration = moment.duration(duration, 'seconds')
			if (duration < 60 * 60) {
				return mDuration.humanize()
			} else if (duration < 60 * 60 * 24) {
				return mDuration.hours() + ' h ' + mDuration.minutes() + ' min'
			} else {
				return mDuration.days() + ' d ' + mDuration.hours() + ' h ' + mDuration.minutes() + ' min'
			}
		},
		getFormattedDistance(distance) {
			if (distance < 1000) {
				return distance + ' m'
			} else {
				return (distance / 1000).toFixed(2) + ' km'
			}
		},
		onRouteFetched(routes) {
			this.routeDuration = null
			this.routeDistance = null
			if (routes && routes.length > 0) {
				this.routeDuration = routes[0].duration
				this.routeDistance = routes[0].distance
			}
		},
		onWaypointChange(waypoints) {
			this.waypoints = waypoints
		},
		onLinkTypeSelect(selected) {
			console.debug('[osm] selected link type', selected)
			this.selectedRoutingLinkTypeId = selected?.id ?? null
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
			const routingLinkType = this.selectedRoutingLinkTypeId
			setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, globe, routingLinkType })
			this.$el.dispatchEvent(new CustomEvent('submit', { detail: this.currentLink, bubbles: true }))
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
.direction-picker-content {
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

	.header {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 4px;

		.profile-select {
			align-self: start;
			margin: 0 !important;
			width: 200px !important;
			min-width: 170px !important;
		}
	}

	.direction-map {
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

		.type-select {
			width: 350px;
			margin: 0;
		}
	}
}
</style>
