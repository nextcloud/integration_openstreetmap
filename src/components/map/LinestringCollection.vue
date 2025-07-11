<script>
import { Popup } from 'maplibre-gl'

export default {
	name: 'LinestringCollection',

	components: {
	},

	props: {
		layerId: {
			type: String,
			default: 'line',
		},
		geojson: {
			type: Object,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		lineWidth: {
			type: Number,
			default: 5,
		},
		borderColor: {
			type: String,
			default: 'black',
		},
		border: {
			type: Boolean,
			default: true,
		},
		opacity: {
			type: Number,
			default: 1,
		},
	},

	emits: ['click'],

	data() {
		return {
			ready: false,
			popup: null,
		}
	},

	computed: {
		borderLayerId() {
			return this.layerId + '-border'
		},
		invisibleBorderLayerId() {
			return this.layerId + '-invisible-border'
		},
		color() {
			return this.geojson.color ?? '#0693e3'
		},
	},

	watch: {
		ready(newVal) {
			if (newVal) {
				this.listenToEvents()
			}
		},
		color(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-color', newVal)
			}
		},
		geojson() {
			console.debug('[osm] geojson has changed')
			this.remove()
			this.init()
		},
		border(newVal) {
			if (newVal) {
				this.drawBorder()
			} else {
				this.removeBorder()
			}
		},
		opacity() {
			console.debug('[osm] line opacity has changed', this.layerId)
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-opacity', this.opacity)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-opacity', this.opacity)
			}
		},
		lineWidth() {
			this.setNormalLineWidth()
		},
	},

	mounted() {
		console.debug('[osm] line mounted', this.layerId)
		this.init()
	},

	unmounted() {
		console.debug('[osm] destroy line', this.layerId)
		this.releaseEvents()
		this.remove()
	},

	methods: {
		bringToTop() {
			console.debug('[osm] bring line to top', this.layerId)
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.moveLayer(this.borderLayerId)
			}
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
		},
		onMouseEnter(e) {
			this.map.getCanvas().style.cursor = 'pointer'
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth * 1.7)
				// this.map.setPaintProperty(this.layerId, 'line-color', 'red')
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', (this.lineWidth * 0.3) * 1.7)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth * 1.7)
			}
			if (this.geojson.popupContent) {
				this.showPopup(e.lngLat, false)
			}
		},
		onMouseLeave() {
			this.map.getCanvas().style.cursor = ''
			this.setNormalLineWidth()
			if (this.popup) {
				this.popup.remove()
				this.popup = null
			}
		},
		onClick() {
			this.$emit('click')
		},
		showPopup(lngLat) {
			if (this.popup) {
				this.popup.remove()
			}
			const html = '<div style="border-color: ' + this.color + ';">'
				+ this.geojson.popupContent.replace('\n', '<br>')
				+ '</div>'
			const popup = new Popup({
				closeButton: false,
				closeOnClick: true,
				closeOnMove: true,
			})
				// .setLngLat(lngLat)
				.trackPointer()
				.setHTML(html)
				.addTo(this.map)
			this.popup = popup
		},
		setNormalLineWidth() {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'line-width', this.lineWidth)
				// this.map.setPaintProperty(this.layerId, 'line-color', this.color)
			}
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.setPaintProperty(this.borderLayerId, 'line-width', this.lineWidth * 0.3)
				this.map.setPaintProperty(this.borderLayerId, 'line-gap-width', this.lineWidth)
			}
		},
		listenToEvents() {
			this.map.on('click', this.invisibleBorderLayerId, this.onClick)
			this.map.on('mouseenter', this.invisibleBorderLayerId, this.onMouseEnter)
			this.map.on('mouseleave', this.invisibleBorderLayerId, this.onMouseLeave)
		},
		releaseEvents() {
			this.map.off('click', this.invisibleBorderLayerId, this.onClick)
			this.map.off('mouseenter', this.invisibleBorderLayerId, this.onMouseEnter)
			this.map.off('mouseleave', this.invisibleBorderLayerId, this.onMouseLeave)
		},
		remove() {
			if (this.map.getLayer(this.invisibleBorderLayerId)) {
				this.map.removeLayer(this.invisibleBorderLayerId)
			}
			this.removeBorder()
			this.removeLine()
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		removeLine() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
			}
		},
		removeBorder() {
			if (this.map.getLayer(this.borderLayerId)) {
				this.map.removeLayer(this.borderLayerId)
			}
		},
		drawBorder() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.borderLayerId,
				paint: {
					'line-color': this.borderColor,
					'line-width': this.lineWidth * 0.3,
					'line-opacity': this.opacity,
					'line-gap-width': this.lineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})
		},
		drawLine() {
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.layerId,
				paint: {
					// 'line-color': ['get', 'color'],
					'line-color': this.color,
					'line-width': this.lineWidth,
					'line-opacity': this.opacity,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
				filter: ['!=', '$type', 'Point'],
			})
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.geojson,
			})
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.invisibleBorderLayerId,
				paint: {
					'line-opacity': 0,
					'line-width': Math.max(this.lineWidth, 30),
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})
			if (this.border) {
				this.drawBorder()
			}
			this.drawLine()

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
