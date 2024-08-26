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
					<span v-if="profileDisplayName" class="profile">
						<component :is="profileIcon" />
						{{ profileDisplayName }}
					</span>
					|
					<span v-if="formattedDistance" :title="richObject.distance">
						{{ t('integration_openstreetmap', 'Distance: {distance}', { distance: formattedDistance }) }}
					</span>
					<span v-else>
						{{ t('integration_openstreetmap', 'Unknown distance') }}
					</span>
					|
					<span v-if="formattedDuration" :title="richObject.duration">
						{{ t('integration_openstreetmap', 'Duration: {duration}', { duration: formattedDuration }) }}
					</span>
					<span v-else>
						{{ t('integration_openstreetmap', 'Unknown duration') }}
					</span>
				</div>
			</div>
			<MaplibreMap
				class="route--map"
				scrolling="no"
				:bbox="bbox"
				:center="mapCenter"
				:zoom="zoom"
				:pitch="pitch"
				:bearing="bearing"
				:map-style="style"
				:use-terrain="useTerrain"
				:markers="richObject.waypoints"
				:lines="routeGeojsons"
				@line-click="onRouteClicked" />
		</div>
	</div>
</template>

<script>
import MarkerIcon from '../components/icons/MarkerIcon.vue'
import MarkerRedIcon from '../components/icons/MarkerRedIcon.vue'
import MarkerGreenIcon from '../components/icons/MarkerGreenIcon.vue'

import MaplibreMap from '../components/map/MaplibreMap.vue'

import { routingProfiles } from '../mapUtils.js'

import moment from '@nextcloud/moment'

export default {
	name: 'MaplibreRouteReferenceWidget',

	components: {
		MarkerGreenIcon,
		MarkerRedIcon,
		MarkerIcon,
		MaplibreMap,
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
		}
	},

	computed: {
		routeGeojsons() {
			return this.richObject.routes.map(r => r.geojson)
		},
		selectedRoute() {
			return this.richObject.routes[this.selectedRouteIndex]
		},
		formattedDuration() {
			if (this.selectedRoute.duration) {
				return this.getFormattedDuration(this.selectedRoute)
			}
			return null
		},
		formattedDistance() {
			if (this.selectedRoute.distance) {
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
			const lats = this.selectedRoute.geojson.features.reduce((acc, value) => {
				return [
					...acc,
					...value.geometry.coordinates.map(p => p[1]),
				]
			}, [])
			const lons = this.selectedRoute.geojson.features.reduce((acc, value) => {
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
		zoom() {
			return this.richObject.zoom
		},
		pitch() {
			return this.richObject.pitch
		},
		bearing() {
			return this.richObject.bearing
		},
		style() {
			return this.richObject.style ?? undefined
		},
		useTerrain() {
			return this.richObject.terrain ?? undefined
		},
		mapCenter() {
			return this.richObject.map_center
		},
	},

	mounted() {
		this.updateRouteOpacities()
		this.setRoutesPopupContent()
		console.debug('[osm] routing mounted', this.richObject)
	},

	methods: {
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
				this.$set(g, 'opacity', 0.3)
			})
			this.$set(this.selectedRoute.geojson, 'opacity', 1)
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
				this.$set(r.geojson, 'popupContent', popupContent.trim())
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
			gap: 10px;
			.profile {
				display: flex;
				align-items: center;
				gap: 4px;
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
