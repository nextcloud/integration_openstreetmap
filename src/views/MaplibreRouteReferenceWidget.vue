<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author 2023 Julien Veyssier <julien-nc@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="route-reference">
		<div class="route">
			<a v-if="richObject.display_name"
				class="route--link"
				:href="richObject.url"
				target="_blank">
				<strong class="route--name">
					{{ richObject.display_name }}
				</strong>
			</a>
			<div class="header">
				<div class="route--steps">
					<span v-for="(w, i) in richObject.waypoints"
						:key="'step-' + i"
						class="step">
						<MarkerGreenIcon v-if="i === 0" />
						<MarkerRedIcon v-else-if="i === richObject.waypoints.length - 1" />
						<MarkerIcon v-else />
						<span>{{ w.name || w.location.join(', ') }}</span>
					</span>
				</div>
				<div class="route--info">
					<NcButton @click="editing = !editing">
						{{ editing ? t('integration_openstreetmap', 'Cancel edition') : t('integration_openstreetmap', 'Edit route') }}
						<template #icon>
							<CloseIcon v-if="editing" />
							<PencilIcon v-else />
						</template>
					</NcButton>
					<NcButton v-if="editing && currentRoutingLink"
						@click="copyRoutingLink">
						{{ t('integration_openstreetmap', 'Copy direction link') }}
						<template #icon>
							<ClipboardCheckOutlineIcon v-if="copied" fill-color="green" />
							<ClipboardTextOutlineIcon v-else />
						</template>
					</NcButton>
					<span v-if="!editing && profileDisplayName" class="profile">
						<component :is="profileIcon" />
						{{ profileDisplayName }}
					</span>
					<RoutingProfilePicker v-else
						v-model="selectedRoutingProfile"
						class="profile-select" />
					<span v-if="formattedDistance" :title="richObject.distance">
						{{ t('integration_openstreetmap', 'Distance: {distance}', { distance: formattedDistance }) }}
					</span>
					<span v-else>
						{{ t('integration_openstreetmap', 'Unknown distance') }}
					</span>
					<span v-if="formattedDuration" :title="richObject.duration">
						{{ t('integration_openstreetmap', 'Duration: {duration}', { duration: formattedDuration }) }}
					</span>
					<span v-else>
						{{ t('integration_openstreetmap', 'Unknown duration') }}
					</span>
				</div>
			</div>
			<MaplibreMap
				:map-style="richObject.style"
				:bbox="bbox"
				:center="richObject.map_center"
				:zoom="richObject.zoom"
				:pitch="richObject.pitch"
				:bearing="richObject.bearing"
				:use-terrain="richObject.terrain"
				:use-globe="richObject.globe"
				class="route--map"
				scrolling="no"
				@line-click="onRouteClicked">
				<template #default="{ map }">
					<DirectionsPlugin v-if="editing"
						:map="map"
						:profile="selectedRoutingProfile?.id ?? undefined"
						:initial-waypoints="richObject.queryPoints.map(p => [p[1], p[0]])"
						@waypoint-change="onWaypointChange"
						@route-fetch="onRouteFetched" />
					<LinestringCollection v-for="(line, i) in (editing ? [] : routeGeojsons)"
						:key="'line-' + i"
						:layer-id="'line-' + i"
						:geojson="line"
						:opacity="line.opacity"
						:map="map"
						@click="onRouteClicked(i)" />
					<VMarker v-for="(m, i) in (editing ? [] : richObject.waypoints)"
						:key="'marker-' + i"
						:map="map"
						:color="m.color"
						:lng-lat="m.location" />
				</template>
			</MaplibreMap>
		</div>
	</div>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline.vue'
import ClipboardTextOutlineIcon from 'vue-material-design-icons/ClipboardTextOutline.vue'

import MarkerIcon from '../components/icons/MarkerIcon.vue'
import MarkerRedIcon from '../components/icons/MarkerRedIcon.vue'
import MarkerGreenIcon from '../components/icons/MarkerGreenIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import MaplibreMap from '../components/map/MaplibreMap.vue'
import DirectionsPlugin from '../components/map/DirectionsPlugin.vue'
import RoutingProfilePicker from '../components/RoutingProfileSelect.vue'
import LinestringCollection from '../components/map/LinestringCollection.vue'
import VMarker from '../components/map/VMarker.vue'

import { routingProfiles, getRoutingLink } from '../mapUtils.js'

import moment from '@nextcloud/moment'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'MaplibreRouteReferenceWidget',

	components: {
		VMarker,
		RoutingProfilePicker,
		NcButton,
		DirectionsPlugin,
		MarkerGreenIcon,
		MarkerRedIcon,
		MarkerIcon,
		MaplibreMap,
		LinestringCollection,
		PencilIcon,
		CloseIcon,
		ClipboardCheckOutlineIcon,
		ClipboardTextOutlineIcon,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			selectedRouteIndex: 0,
			editing: false,
			editingWaypoints: [],
			editingRoute: null,
			copied: false,
			selectedRoutingProfile: routingProfiles[this.richObject.profile] ?? routingProfiles.car,
		}
	},

	computed: {
		routeGeojsons() {
			return this.richObject.routes.map(r => r.geojson)
		},
		selectedRoute() {
			return this.editing
				? this.editingRoute
				: this.richObject.routes[this.selectedRouteIndex]
		},
		formattedDuration() {
			if (this.selectedRoute?.duration) {
				return this.getFormattedDuration(this.selectedRoute)
			}
			return null
		},
		formattedDistance() {
			if (this.selectedRoute?.distance) {
				return this.getFormattedDistance(this.selectedRoute)
			}
			return null
		},
		profileIcon() {
			if (this.richObject.profile) {
				return routingProfiles[this.richObject.profile]
					? routingProfiles[this.richObject.profile].icon
					: routingProfiles.car.icon
			}
			return null
		},
		profileDisplayName() {
			if (this.richObject.profile) {
				return routingProfiles[this.richObject.profile]
					? routingProfiles[this.richObject.profile].label
					: routingProfiles.car.label
			}
			return null
		},
		bbox() {
			// use mapCenter in priority
			if (this.mapCenter) {
				return undefined
			}
			if (this.richObject.routes.length === 0) {
				return undefined
			}
			const route = this.richObject.routes[this.selectedRouteIndex]
			const lats = route.geojson.features.reduce((acc, value) => {
				return [
					...acc,
					...value.geometry.coordinates.map(p => p[1]),
				]
			}, [])
			const lons = route.geojson.features.reduce((acc, value) => {
				return [
					...acc,
					...value.geometry.coordinates.map(p => p[0]),
				]
			}, [])
			return {
				north: lats.reduce((acc, val) => Math.max(acc, val)),
				south: lats.reduce((acc, val) => Math.min(acc, val)),
				east: lons.reduce((acc, val) => Math.max(acc, val)),
				west: lons.reduce((acc, val) => Math.min(acc, val)),
			}
		},
		currentRoutingLink() {
			if (!this.editing) {
				return
			}
			return getRoutingLink(this.editingWaypoints, this.selectedRoutingProfile, this.richObject.type)
		},
	},

	mounted() {
		this.updateRouteOpacities()
		this.setRoutesPopupContent()
		console.debug('[osm] routing mounted', this.richObject)
	},

	methods: {
		async copyRoutingLink() {
			console.debug('[osm] copy link', this.currentRoutingLink)
			try {
				await navigator.clipboard.writeText(this.currentRoutingLink)
				this.copied = true
				setTimeout(() => {
					this.copied = false
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('integration_openstreetmap', 'Link could not be copied to clipboard'))
			}
		},
		onWaypointChange(waypoints) {
			this.editingWaypoints = waypoints
		},
		onRouteFetched(routes) {
			if (routes && routes.length > 0) {
				this.editingRoute = routes[0]
			} else {
				this.editingRoute = null
			}
		},
		getFormattedDuration(route) {
			const mDuration = moment.duration(route.duration, 'seconds')
			if (route.duration < 60 * 60) {
				return mDuration.humanize()
			} else if (route.duration < 60 * 60 * 24) {
				return mDuration.hours() + ' h ' + mDuration.minutes() + ' min'
			} else {
				return mDuration.days() + ' d ' + mDuration.hours() + ' h ' + mDuration.minutes() + ' min'
			}
		},
		getFormattedDistance(route) {
			if (route.distance < 1000) {
				return route.distance + ' m'
			} else {
				return (route.distance / 1000).toFixed(2) + ' km'
			}
		},
		onRouteClicked(index) {
			this.selectedRouteIndex = index
			this.updateRouteOpacities()
		},
		updateRouteOpacities() {
			this.routeGeojsons.forEach(g => {
				g.opacity = 0.3
			})
			this.routeGeojsons[this.selectedRouteIndex].opacity = 1
		},
		setRoutesPopupContent() {
			this.richObject.routes.forEach(r => {
				let popupContent = ''
				if (r.distance) {
					popupContent += '\n' + t('integration_openstreetmap', 'Distance: {distance}', { distance: this.getFormattedDistance(r) })
				}
				if (r.duration) {
					popupContent += '\n' + t('integration_openstreetmap', 'Duration: {duration}', { duration: this.getFormattedDuration(r) })
				}
				r.geojson.popupContent = popupContent.trim()
			})
		},
	},
}
</script>

<style scoped lang="scss">
.route-reference {
	width: 100%;
	white-space: normal;

	.route {
		width: 100%;
		display: flex;
		flex-direction: column;
		// in case there is an absolute inside
		position: relative;

		.header {
			margin: 4px;
		}

		&--link {
			padding: 10px;
			&:hover {
				color: #58a6ff !important;
			}
		}

		&--info {
			display: flex;
			align-items: center;
			gap: 10px;
			.profile {
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.profile-select {
				margin: 0 !important;
				width: 200px !important;
				min-width: 170px !important;
			}
		}

		&--steps {
			display: flex;
			//flex-direction: column;
			flex-wrap: wrap;
			gap: 4px;
			.step {
				display: flex;
				gap: 4px;
			}
		}

		&--map {
			width: 100%;
			height: 350px;
		}
	}
}
</style>
