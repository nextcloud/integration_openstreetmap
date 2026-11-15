<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="map-wrapper">
		<a v-if="showWatermark"
			href="https://www.maptiler.com"
			class="watermark"
			:class="{ padded: showMousePositionControl }">
			<img :src="watermarkUrl"
				alt="MapTiler logo">
		</a>
		<div
			ref="mapContainer"
			class="osm-integration-map" />
		<div v-if="map"
			class="map-content">
			<!-- some stuff go away when changing the style -->
			<div v-if="mapLoaded">
				<slot name="default" :map="map" />
			</div>
		</div>
	</div>
</template>

<script>
import { markRaw } from 'vue'
import * as maplibregl from 'maplibre-gl'
import {
	Map, NavigationControl, ScaleControl, GeolocateControl,
	FullscreenControl, setWorkerUrl,
} from 'maplibre-gl'
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import MaplibreGeocoder from '@maplibre/maplibre-gl-geocoder'
import '@maplibre/maplibre-gl-geocoder/dist/maplibre-gl-geocoder.css'

setWorkerUrl(workerUrl)

import {
	getRasterTileServers,
	getVectorStyles,
	getOpenFreeMapStyles,
} from './tileServers.js'
import { MousePositionControl, TileControl, GlobeControl, TerrainControl, CollapsedAttributionControl } from './mapControls.js'
import { forwardGeocodeNominatim } from './nominatimGeocoder.js'
import { defaultMapImages } from './mapImages.js'

import '../../css/maplibre.scss'

const DEFAULT_MAP_MAX_ZOOM = 22
const DEFAULT_MAPTILER_TERRAIN_URL = 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key='
const DEFAULT_MAPTILER_LOGO_URL = 'https://api.maptiler.com/resources/logo.svg'

export default {
	name: 'MaplibreMap',

	components: {
	},

	props: {
		// MapTiler API key, required for the MapTiler vector styles and the terrain
		maptilerApiKey: {
			type: String,
			default: null,
		},
		// optional style override, { styleKey: { title, uri|style, maxzoom? } }
		// merged over the default direct tile servers
		styles: {
			type: Object,
			default: null,
		},
		// optional map images override, { imageKey: url }
		// merged over the default built-in images
		images: {
			type: Object,
			default: null,
		},
		// optional geocoder options, { forwardGeocode?, placeholder? }
		// missing entries fall back to the built-in direct Nominatim geocoder
		geocoderOptions: {
			type: Object,
			default: null,
		},
		// optional terrain tiles.json URL override
		terrainTilesUrl: {
			type: String,
			default: null,
		},
		// optional MapTiler logo URL override, only used when a MapTiler style is selected
		logoUrl: {
			type: String,
			default: null,
		},
		useTerrain: {
			type: Boolean,
			default: false,
		},
		terrainScale: {
			type: Number,
			default: 2.5,
		},
		useGlobe: {
			type: Boolean,
			default: false,
		},
		useSky: {
			type: Boolean,
			default: true,
		},
		bbox: {
			type: Object,
			default: null,
		},
		center: {
			type: Object,
			default: null,
		},
		zoom: {
			type: Number,
			default: null,
		},
		pitch: {
			type: Number,
			default: null,
		},
		bearing: {
			type: Number,
			default: null,
		},
		mapStyle: {
			type: String,
			default: undefined,
		},
		unit: {
			type: String,
			default: 'metric',
		},
		showMousePositionControl: {
			type: Boolean,
			default: false,
		},
		showSearchControl: {
			type: Boolean,
			default: true,
		},
		allMoveEvents: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:center',
		'update:zoom',
		'update:pitch',
		'update:bearing',
		'update:mapStyle',
		'update:useTerrain',
		'update:useGlobe',
		'map-bounds-change',
	],

	data() {
		return {
			map: null,
			myStyles: {},
			myStyleKey: null,
			mapLoaded: false,
			defaultStyleKey: 'openfreemapLiberty',
			mousePositionControl: null,
			scaleControl: null,
			geocoderControl: null,
			myUseTerrain: this.useTerrain,
			terrainControl: null,
			globeControl: null,
			myUseGlobe: this.useGlobe,
		}
	},

	computed: {
		hasMaptilerApiKey() {
			return !!this.maptilerApiKey
		},
		watermarkUrl() {
			return this.logoUrl ?? DEFAULT_MAPTILER_LOGO_URL
		},
		showWatermark() {
			// MapTiler requires their logo when their tiles are used,
			// the logo styles are the ones tagged with `maptiler`
			return !!this.myStyles[this.myStyleKey]?.maptiler
		},
	},

	watch: {
		showMousePositionControl(newValue) {
			if (newValue) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			} else {
				this.map.removeControl(this.mousePositionControl)
			}
		},
		showSearchControl(newValue) {
			if (!this.map) {
				return
			}
			if (newValue) {
				if (!this.geocoderControl) {
					this.createGeocoder()
				}
				this.map.addControl(this.geocoderControl, 'top-left')
			} else {
				this.map.removeControl(this.geocoderControl)
			}
		},
		unit(newValue) {
			this.scaleControl?.setUnit(newValue)
		},
		useTerrain(newValue) {
			console.debug('watch useterrain', newValue, this.myUseTerrain)
			// ignore if the internal state is already the same as the changing prop
			if (this.myUseTerrain === newValue) {
				return
			}
			this.myUseTerrain = newValue
			this.toggleTerrain()
		},
		terrainScale(newValue) {
			console.debug('watch terrain scale', newValue)
			if (this.myUseTerrain) {
				this.enableTerrain()
			}
		},
		useGlobe(newValue) {
			if (this.myUseGlobe === newValue) {
				return
			}
			this.myUseGlobe = newValue
			this.map.setProjection({
				type: newValue ? 'globe' : 'mercator',
			})
			this.globeControl.updateGlobeIcon(newValue)
		},
		useSky(newValue) {
			if (newValue) {
				this.setSky()
			} else {
				this.map.setSky(undefined)
			}
		},
	},

	mounted() {
		this.initMap()
	},

	unmounted() {
		this.map.remove()
	},

	methods: {
		createGeocoder() {
			const geocoderOptions = this.geocoderOptions ?? {}
			this.geocoderControl = new MaplibreGeocoder(
				{ forwardGeocode: geocoderOptions.forwardGeocode ?? forwardGeocodeNominatim },
				{
					maplibregl,
					placeholder: geocoderOptions.placeholder ?? 'Search',
					minLength: 4,
					debounceSearch: 400,
					popup: true,
					showResultsWhileTyping: true,
					flyTo: {
						pitch: 0,
						animate: window.matchMedia('(prefers-reduced-motion: reduce)').matches
							? false
							: undefined,
					},
				},
			)
		},
		initMap() {
			const apiKey = this.maptilerApiKey
			// default vector style, available without any API key
			this.defaultStyleKey = apiKey ? 'streets' : 'openfreemapLiberty'
			// tile servers and styles
			const vectorStyles = apiKey ? getVectorStyles(apiKey) : {}
			this.myStyles = {
				// always available, no API key required
				...getOpenFreeMapStyles(),
				...vectorStyles,
				...getRasterTileServers(apiKey),
				...(this.styles ?? {}),
			}
			const restoredStyleKey = Object.keys(this.styles).includes(this.mapStyle)
				? this.mapStyle
				: this.defaultStyleKey
			const restoredStyleObj = this.styles[restoredStyleKey]
			this.myStyleKey = restoredStyleKey
			this.$emit('update:mapStyle', restoredStyleKey)

			const mapOptions = {
				container: this.$refs.mapContainer,
				style: restoredStyleObj.uri ? restoredStyleObj.uri : restoredStyleObj,
				maxPitch: 75,
				// added manually below, so it can start collapsed
				attributionControl: false,
				maxZoom: restoredStyleObj.maxzoom ? (restoredStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM,
				transformRequest: (url, resourceType) => {
					return {
						url,
						referrerPolicy: 'origin-when-cross-origin',
					}
				},
			}
			this.map = markRaw(new Map(mapOptions))
			// attribution, collapsed into the info button by default
			this.map.addControl(new CollapsedAttributionControl())
			if (this.bbox) {
				const nsew = this.bbox
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 16,
					animate: false,
				})
			}
			if (this.zoom !== null) {
				this.map.setZoom(this.zoom)
			}
			if (this.center !== null) {
				this.map.setCenter(this.center)
			}
			if (this.pitch !== null) {
				this.map.setPitch(this.pitch)
			}
			if (this.bearing !== null) {
				this.map.setBearing(this.bearing)
			}
			const navigationControl = new NavigationControl({ visualizePitch: true })
			this.scaleControl = new ScaleControl({ unit: this.unit })

			if (this.showSearchControl) {
				this.createGeocoder()
				this.map.addControl(this.geocoderControl, 'top-left')
			}

			const geolocateControl = new GeolocateControl({
				trackUserLocation: true,
				positionOptions: {
					enableHighAccuracy: true,
					timeout: 10000,
				},
			})
			const fullscreenControl = new FullscreenControl()
			this.map.addControl(this.scaleControl, 'top-left')
			this.map.addControl(geolocateControl, 'top-left')
			this.map.addControl(navigationControl, 'top-left')

			// mouse position
			this.mousePositionControl = new MousePositionControl()
			if (this.showMousePositionControl) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			}

			// custom tile control
			const tileControl = new TileControl({ styles: this.myStyles, selectedKey: restoredStyleKey })
			tileControl.on('changeStyle', (key) => {
				this.myStyleKey = key
				this.$emit('update:mapStyle', key)
			})
			this.map.addControl(tileControl, 'top-right')
			this.map.addControl(fullscreenControl, 'top-right')

			if (this.hasMaptilerApiKey) {
				this.terrainControl = new TerrainControl()
				this.terrainControl.on('toggleTerrain', this.toggleTerrain)
				this.map.addControl(this.terrainControl, 'top-right')
			}

			this.globeControl = new GlobeControl()
			this.globeControl.on('toggleGlobe', this.toggleGlobe)
			this.map.addControl(this.globeControl, 'top-right')

			this.map.on('style.load', () => {
				this.map.setProjection({
					type: this.myUseGlobe ? 'globe' : 'mercator',
				})

				if (this.useSky) {
					this.setSky()
				}

				// max zoom
				const styleKey = Object.keys(this.myStyles).includes(this.mapStyle) ? this.mapStyle : this.defaultStyleKey
				const mapStyleObj = this.myStyles[styleKey]
				const maxZoom = mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM
				console.debug('apply max ZOOM', maxZoom, mapStyleObj)
				this.map.setMaxZoom(maxZoom)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})

			this.handleMapEvents()

			this.map.on('load', () => {
				this.loadImages()

				if (this.hasMaptilerApiKey) {
					this.terrainControl.updateTerrainIcon(this.myUseTerrain)
				}
				this.globeControl.updateGlobeIcon(this.myUseGlobe)

				setTimeout(() => {
					this.emitMapState()
					this.emitMapBounds()
				}, 300)
			})
		},
		loadImages() {
			const mapImages = { ...defaultMapImages, ...(this.images ?? {}) }
			// this is needed when switching between vector and raster tile servers, the image is sometimes not removed
			for (const imgKey in mapImages) {
				if (this.map.hasImage(imgKey)) {
					this.map.removeImage(imgKey)
				}
			}
			const loadImagePromises = Object.keys(mapImages).map((k) => {
				return this.loadVectorImage(k, mapImages[k])
			})
			Promise.allSettled(loadImagePromises)
				.then((promises) => {
					// tracks are waiting for that to load
					this.mapLoaded = true
					promises.forEach(p => {
						if (p.status === 'rejected') {
							console.error(p.reason?.message)
						}
					})
				})
		},
		loadVectorImage(imgKey, src) {
			return new Promise((resolve, reject) => {
				const svgIcon = new Image(41, 41)
				svgIcon.onload = () => {
					this.map.addImage(imgKey, svgIcon)
					resolve()
				}
				svgIcon.onerror = () => {
					reject(new Error('Failed to load ' + imgKey))
				}
				svgIcon.src = src
			})
		},
		toggleGlobe() {
			this.myUseGlobe = !this.myUseGlobe
			console.debug('toggleGlobe', this.myUseGlobe)
			this.$emit('update:useGlobe', this.myUseGlobe)
			this.map.setProjection({
				type: this.myUseGlobe ? 'globe' : 'mercator',
			})
			this.globeControl.updateGlobeIcon(this.myUseGlobe)
		},
		setSky() {
			// https://maplibre.org/maplibre-style-spec/sky/
			this.map.setSky({
				'sky-color': '#199EF3',
				'sky-horizon-blend': 0.5,
				'horizon-color': '#ffffff',
				'horizon-fog-blend': 0.5,
				'fog-color': '#0000ff',
				'fog-ground-blend': 0.5,
				'atmosphere-blend': 0,
			})
		},
		reRenderLayersAndTerrain() {
			// re render the layers
			this.mapLoaded = false
			this.loadImages()
			if (this.hasMaptilerApiKey && this.myUseTerrain) {
				this.enableTerrain()
			}
		},
		toggleTerrain() {
			if (!this.hasMaptilerApiKey) {
				return
			}
			this.myUseTerrain = !this.myUseTerrain
			this.$emit('update:useTerrain', this.myUseTerrain)
			if (this.myUseTerrain) {
				this.enableTerrain()
			} else {
				this.disableTerrain()
			}
			this.terrainControl.updateTerrainIcon(this.myUseTerrain)
		},
		enableTerrain() {
			if (!this.hasMaptilerApiKey) {
				return
			}
			this.addTerrainSource()
			this.map.setTerrain({
				source: 'terrain',
				exaggeration: this.terrainScale,
			})
		},
		disableTerrain() {
			if (!this.hasMaptilerApiKey) {
				return
			}
			this.map.setTerrain()
		},
		addTerrainSource() {
			if (!this.hasMaptilerApiKey) {
				return
			}
			if (this.map.getSource('terrain')) {
				return
			}
			const url = this.terrainTilesUrl
				?? (DEFAULT_MAPTILER_TERRAIN_URL + this.maptilerApiKey)
			this.map.addSource('terrain', {
				type: 'raster-dem',
				url,
			})
		},
		handleMapEvents() {
			this.map.on('moveend', () => {
				this.emitMapState()
				this.emitMapBounds()
			})
			if (this.allMoveEvents) {
				this.map.on('move', () => {
					this.emitMapState()
				})
			}
		},
		emitMapState() {
			const { lng, lat } = this.map.getCenter()
			this.$emit('update:center', { lat, lng })
			this.$emit('update:zoom', this.map.getZoom())
			this.$emit('update:pitch', this.map.getPitch())
			this.$emit('update:bearing', this.map.getBearing())
		},
		emitMapBounds() {
			const bounds = this.map.getBounds()
			this.$emit('map-bounds-change', {
				north: bounds.getNorth(),
				east: bounds.getEast(),
				south: bounds.getSouth(),
				west: bounds.getWest(),
			})
		},
		onZoomOn(nsew) {
			if (this.map) {
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
				})
			}
		},
	},
}
</script>

<style lang="scss">
@import 'maplibre-gl/dist/maplibre-gl.css';
</style>

<style scoped lang="scss">

.map-wrapper {
	position: relative;
	width: 100%;
	height: 100%;

	.osm-integration-map {
		width: 100%;
		height: 100%;
	}

	.watermark {
		position: absolute;
		z-index: 999;
		left: 10px;
		bottom: 0;
		&.padded {
			bottom: 18px;
		}
	}
}
</style>
