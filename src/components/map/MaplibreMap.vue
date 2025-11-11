<template>
	<div class="map-wrapper">
		<a href="https://www.maptiler.com" class="watermark" :class="{ padded: showMousePositionControl }">
			<img :src="maptilerLogoUrl"
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
import maplibregl, {
	Map, NavigationControl, ScaleControl, GeolocateControl,
	FullscreenControl,
} from 'maplibre-gl'
import MaplibreGeocoder from '@maplibre/maplibre-gl-geocoder'
import '@maplibre/maplibre-gl-geocoder/dist/maplibre-gl-geocoder.css'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import {
	getRasterTileServers,
	getVectorStyles,
} from '../../tileServers.js'
import { MousePositionControl, TileControl, GlobeControl, TerrainControl } from '../../mapControls.js'
import { maplibreForwardGeocode, mapVectorImages } from '../../mapUtils.js'

import '../../../css/maplibre.scss'

const DEFAULT_MAP_MAX_ZOOM = 22

export default {
	name: 'MaplibreMap',

	components: {
	},

	props: {
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
			default: 'streets',
		},
		area: {
			type: Object,
			default: null,
		},
		unit: {
			type: String,
			default: 'metric',
		},
		showMousePositionControl: {
			type: Boolean,
			default: false,
		},
		allMoveEvents: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'line-click',
		'map-bounds-change',
	],

	data() {
		return {
			map: null,
			styles: {},
			mapLoaded: false,
			mousePositionControl: null,
			scaleControl: null,
			myUseTerrain: this.useTerrain,
			terrainControl: null,
			globeControl: null,
			myUseGlobe: this.useGlobe,
			apiKeys: loadState('integration_openstreetmap', 'api-keys'),
			// https://api.maptiler.com/resources/logo.svg
			maptilerLogoUrl: generateUrl('/apps/integration_openstreetmap/maptiler/resources/logo.svg'),
		}
	},

	computed: {
	},

	watch: {
		showMousePositionControl(newValue) {
			if (newValue) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			} else {
				this.map.removeControl(this.mousePositionControl)
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
		/*
		pitch(newValue) {
			this.map.setPitch(newValue)
		},
		bearing(newValue) {
			this.map.setBearing(newValue)
		},
		mapStyle(newValue) {
			console.debug('mapStyle changed', newValue)
		},
		*/
	},

	mounted() {
		this.initMap()
	},

	unmounted() {
		this.map.remove()
	},

	methods: {
		initMap() {
			const apiKey = this.apiKeys.maptiler_api_key
			// tile servers and styles
			this.styles = {
				...getVectorStyles(apiKey),
				...getRasterTileServers(apiKey),
			}
			const restoredStyleKey = Object.keys(this.styles).includes(this.mapStyle) ? this.mapStyle : 'streets'
			const restoredStyleObj = this.styles[restoredStyleKey]
			this.$emit('update:mapStyle', restoredStyleKey)

			const mapOptions = {
				container: this.$refs.mapContainer,
				style: restoredStyleObj.uri ? restoredStyleObj.uri : restoredStyleObj,
				// center: centerLngLat,
				// zoom: this.settings.zoom ?? 1,
				// pitch: this.settings.pitch ?? 0,
				// bearing: this.settings.bearing ?? 0,
				// bounds,
				maxPitch: 75,
				maxZoom: restoredStyleObj.maxzoom ? (restoredStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM,
			}
			this.map = new Map(mapOptions)
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

			this.map.addControl(
				new MaplibreGeocoder({ forwardGeocode: maplibreForwardGeocode }, {
					maplibregl,
					placeholder: t('integration_openstreetmap', 'Search on the map'),
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
				}),
				'top-left',
			)

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
			const tileControl = new TileControl({ styles: this.styles, selectedKey: restoredStyleKey })
			tileControl.on('changeStyle', (key) => {
				this.$emit('update:mapStyle', key)
			})
			this.map.addControl(tileControl, 'top-right')
			this.map.addControl(fullscreenControl, 'top-right')

			this.terrainControl = new TerrainControl()
			this.terrainControl.on('toggleTerrain', this.toggleTerrain)
			this.map.addControl(this.terrainControl, 'top-right')

			this.globeControl = new GlobeControl()
			this.globeControl.on('toggleGlobe', this.toggleGlobe)
			this.map.addControl(this.globeControl, 'top-right')

			this.map.on('style.load', () => {
				this.map.setProjection({
					type: this.myUseGlobe ? 'globe' : 'mercator',
				})

				// max zoom
				const styleKey = Object.keys(this.styles).includes(this.mapStyle) ? this.mapStyle : 'streets'
				const mapStyleObj = this.styles[styleKey]
				const maxZoom = mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM
				console.debug('apply max ZOOM', maxZoom, mapStyleObj)
				this.map.setMaxZoom(maxZoom)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})

			this.handleMapEvents()

			this.map.on('load', () => {
				this.loadImages()

				this.terrainControl.updateTerrainIcon(this.myUseTerrain)
				this.globeControl.updateGlobeIcon(this.myUseGlobe)

				setTimeout(() => {
					this.emitMapState()
					this.emitMapBounds()
				}, 300)
			})
		},
		loadImages() {
			// this is needed when switching between vector and raster tile servers, the image is sometimes not removed
			for (const imgKey in mapVectorImages) {
				if (this.map.hasImage(imgKey)) {
					this.map.removeImage(imgKey)
				}
			}
			const loadImagePromises = Object.keys(mapVectorImages).map((k) => {
				return this.loadVectorImage(k)
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
		loadVectorImage(imgKey) {
			return new Promise((resolve, reject) => {
				const svgIcon = new Image(41, 41)
				svgIcon.onload = () => {
					this.map.addImage(imgKey, svgIcon)
					resolve()
				}
				svgIcon.onerror = () => {
					reject(new Error('Failed to load ' + imgKey))
				}
				svgIcon.src = imagePath('integration_openstreetmap', mapVectorImages[imgKey])
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
		reRenderLayersAndTerrain() {
			// re render the layers
			this.mapLoaded = false
			this.loadImages()
			if (this.myUseTerrain) {
				this.enableTerrain()
			}
		},
		toggleTerrain() {
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
			this.addTerrainSource()
			this.map.setTerrain({
				source: 'terrain',
				exaggeration: this.terrainScale,
			})
		},
		disableTerrain() {
			this.map.setTerrain()
		},
		addTerrainSource() {
			if (this.map.getSource('terrain')) {
				return
			}
			const apiKey = this.apiKeys.maptiler_api_key
			this.map.addSource('terrain', {
				type: 'raster-dem',
				// url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + apiKey,
				url: generateUrl('/apps/integration_openstreetmap/maptiler/tiles/terrain-rgb-v2/tiles.json?key=' + apiKey),
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
