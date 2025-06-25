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
			<NcButton :title="helpText">
				<template #icon>
					<HelpIcon />
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
			class="direction-map"
			:center="lastCenter"
			:zoom="lastMapState?.zoom"
			:pitch="lastMapState?.pitch"
			:bearing="lastMapState?.bearing"
			:map-style="lastMapState?.mapStyle"
			:use-terrain="!!lastMapState?.terrain"
			:all-move-events="true"
			@map-state-change="onMapStateChange">
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
				:options="linkTypesArray"
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
import HelpIcon from 'vue-material-design-icons/Help.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import MaplibreMap from '../components/map/MaplibreMap.vue'
import DirectionsPlugin from '../components/map/DirectionsPlugin.vue'
import RoutingProfilePicker from '../components/RoutingProfileSelect.vue'

import moment from '@nextcloud/moment'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'
import { routingProfiles, routingLinkTypes, getRoutingLink } from '../mapUtils.js'

const linkTypesArray = Object.values(routingLinkTypes)

export default {
	name: 'DirectionCustomPickerElement',

	components: {
		RoutingProfilePicker,
		DirectionsPlugin,
		MaplibreMap,
		NcButton,
		NcSelect,
		ArrowRightIcon,
		HelpIcon,
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
			lastMapState: getLastMapState(),
			searchPlaceholder: t('integration_openstreetmap', 'Search with Nominatim to get an OpenStreetMap link'),
			linkTypesArray,
			waypoints: null,
			routeDistance: null,
			routeDuration: null,
			selectedProfile: routingProfiles.car,
			helpText: t('integration_openstreetmap', 'Click on the map to add waypoints. Click on waypoints to delete them. Waypoints can be dragged.'),
		}
	},

	computed: {
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
		lastCenter() {
			if (this.lastMapState?.lat && this.lastMapState?.lon) {
				return {
					lat: this.lastMapState.lat,
					lon: this.lastMapState.lon,
				}
			}
			return null
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
			const link = getRoutingLink(this.waypoints, this.selectedProfile, this.selectedRoutingLinkTypeId)
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
			const lat = this.currentCenter.lat
			const lon = this.currentCenter.lon
			const zoom = this.currentZoom
			const pitch = this.currentPitch
			const bearing = this.currentBearing ? parseFloat(this.currentBearing.toFixed(2)) : this.currentBearing
			const mapStyle = this.currentMapStyle
			const terrain = this.currentMapTerrain ? '1' : ''
			const routingLinkType = this.selectedRoutingLinkTypeId
			setLastMapState({ lat, lon, zoom, pitch, bearing, mapStyle, terrain, routingLinkType })
			this.$el.dispatchEvent(new CustomEvent('submit', { detail: this.currentLink, bubbles: true }))
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
