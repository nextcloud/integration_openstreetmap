<template>
	<div class="direction-picker-content">
		<h2>
			{{ t('integration_openstreetmap', 'Map directions (by OSRM)') }}
		</h2>
		<div class="header">
			<NcSelect
				v-model="selectedProfile"
				class="profile-select"
				:options="profiles"
				:aria-label-combobox="t('integration_openstreetmap', 'Routing profile')"
				:placeholder="t('integration_openstreetmap', 'Routing profile')" />
			<div v-if="formattedDistance && formattedDuration" class="route--info">
				<span :title="routeDistance">
					{{ t('integration_openstreetmap', 'Distance: {distance}', { distance: formattedDistance }) }}
				</span>
				|
				<span :title="routeDuration">
					{{ t('integration_openstreetmap', 'Duration: {duration}', { duration: formattedDuration }) }}
				</span>
			</div>
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
			:zoom="lastMapState?.zoom ?? undefined"
			:pitch="lastMapState?.pitch ?? undefined"
			:bearing="lastMapState?.bearing ?? undefined"
			:map-style="lastMapState?.mapStyle ?? undefined"
			:use-terrain="!!lastMapState?.terrain ?? undefined"
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
			<NcSelect
				class="type-select"
				:value="selectedLinkType"
				:options="linkTypesArray"
				:aria-label-combobox="t('integration_openstreetmap', 'Link type')"
				:placeholder="t('integration_openstreetmap', 'Link type')"
				input-id="extension-select"
				@input="onLinkTypeSelect" />
			<div class="spacer" />
			<NcButton
				class="submit-button"
				type="primary"
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import MaplibreMap from '../components/map/MaplibreMap.vue'
import DirectionsPlugin from '../components/map/DirectionsPlugin.vue'

import moment from '@nextcloud/moment'

import { getLastMapState, setLastMapState } from '../lastMapStateHelper.js'

const linkTypes = {
	osrm_osm_de: {
		id: 'osrm_osm_de',
		label: 'https://routing.openstreetmap.de',
	},
	osrm_org: {
		id: 'osrm_org',
		label: 'https://map.project-osrm.org',
	},
	graphhopper_com: {
		id: 'graphhopper_com',
		label: 'https://graphhopper.com/maps',
	},
}
const linkTypesArray = Object.keys(linkTypes).map(typeId => linkTypes[typeId])

const profiles = [
	{
		id: 'routed-car',
		label: t('integration_openstreetmap', 'Car'),
		srv: 0,
		ghpProfile: 'car',
	},
	{
		id: 'routed-bike',
		label: t('integration_openstreetmap', 'Bike'),
		srv: 1,
		ghpProfile: 'bike',
	},
	{
		id: 'routed-foot',
		label: t('integration_openstreetmap', 'Foot'),
		srv: 2,
		ghpProfile: 'foot',
	},
]

export default {
	name: 'DirectionCustomPickerElement',

	components: {
		DirectionsPlugin,
		MaplibreMap,
		NcButton,
		NcSelect,
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
			selectedRoutingLinkTypeId: getLastMapState()?.routingLinkType ? getLastMapState().routingLinkType : linkTypes.osrm_org.id,
			showMap: false,
			lastMapState: getLastMapState(),
			searchPlaceholder: t('integration_openstreetmap', 'Search with Nominatim to get an OpenStreetMap link'),
			linkTypesArray,
			waypoints: null,
			routeDistance: null,
			routeDuration: null,
			profiles,
			selectedProfile: profiles[0],
		}
	},

	computed: {
		selectedProfileId() {
			if (this.selectedProfile) {
				return this.selectedProfile.id
			}
			return undefined
		},
		selectedLinkType() {
			return linkTypes[this.selectedRoutingLinkTypeId] ?? null
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
		currentLink() {
			if (this.waypoints === null || this.waypoints.length < 2) {
				return null
			}
			console.debug('currentLink www', this.waypoints)

			let link
			const fragments = []
			const queryParams = []
			if (this.selectedRoutingLinkTypeId === null || this.selectedRoutingLinkTypeId === linkTypes.osrm_org.id) {
				link = 'https://map.project-osrm.org/'
				queryParams.push(...this.waypoints.map(w => `loc=${w[1]}%2C${w[0]}`))
				queryParams.push('srv=' + this.selectedProfile.srv)
			} else if (this.selectedRoutingLinkTypeId === linkTypes.osrm_osm_de.id) {
				link = 'https://routing.openstreetmap.de/'
				queryParams.push(...this.waypoints.map(w => `loc=${w[1]}%2C${w[0]}`))
				queryParams.push('srv=' + this.selectedProfile.srv)
			} else if (this.selectedRoutingLinkTypeId === linkTypes.graphhopper_com.id) {
				link = 'https://graphhopper.com/maps/'
				queryParams.push(...this.waypoints.map(w => `point=${w[1]}%2C${w[0]}`))
				queryParams.push('profile=' + this.selectedProfile.ghpProfile)
			}

			if (queryParams.length > 0) {
				link += '?' + queryParams.join('&')
			}
			if (fragments.length > 0) {
				link += '#' + fragments.join('&')
			}

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
			this.$emit('submit', this.currentLink)
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
	}

	.header {
		width: 100%;
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 4px;

		.profile-select {
			align-self: start;
			margin: 0 !important;
			width: 170px !important;
			min-width: 170px !important;
		}
	}

	.direction-map {
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

		.type-select {
			width: 350px;
		}
	}
}
</style>
