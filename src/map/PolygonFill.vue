<script>
export default {
	name: 'PolygonFill',

	components: {
	},

	mixins: [
	],

	props: {
		layerId: {
			type: String,
			required: true,
		},
		geojson: {
			type: Object,
			required: true,
		},
		map: {
			type: Object,
			required: true,
		},
		fillOutlineColor: {
			type: String,
			default: 'blue',
		},
		fillColor: {
			type: String,
			default: 'lightblue',
		},
		fillOpacity: {
			type: Number,
			default: 0.5,
		},
		contourLineWidth: {
			type: Number,
			default: 4,
		},
	},

	data() {
		return {
			ready: false,
		}
	},

	computed: {
		shouldFill() {
			return this.geojson.type === 'Polygon'
		},
	},

	watch: {
		fillColor(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'fill-color', newVal)
			}
		},
		fillOpacity(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'fill-opacity', newVal)
			}
		},
		fillOutlineColor(newVal) {
			if (this.map.getLayer(this.layerId)) {
				this.map.setPaintProperty(this.layerId, 'fill-outline-color', newVal)
			}
		},
		geojson() {
			this.remove()
			this.init()
		},
	},

	mounted() {
		this.init()
	},

	unmounted() {
		this.remove()
	},

	methods: {
		bringToTop() {
			if (this.map.getLayer(this.layerId)) {
				this.map.moveLayer(this.layerId)
			}
		},
		remove() {
			if (this.map.getLayer(this.layerId)) {
				this.map.removeLayer(this.layerId)
			}
			if (this.map.getLayer(this.layerId + '-contour')) {
				this.map.removeLayer(this.layerId + '-contour')
			}
			if (this.map.getSource(this.layerId)) {
				this.map.removeSource(this.layerId)
			}
		},
		init() {
			this.map.addSource(this.layerId, {
				type: 'geojson',
				lineMetrics: true,
				data: this.geojson,
			})
			if (this.shouldFill) {
				this.map.addLayer({
					type: 'fill',
					source: this.layerId,
					id: this.layerId,
					paint: {
						'fill-color': this.fillColor,
						'fill-opacity': this.fillOpacity,
						'fill-outline-color': this.fillOutlineColor,
					},
				})
			}
			this.map.addLayer({
				type: 'line',
				source: this.layerId,
				id: this.layerId + '-contour',
				paint: {
					'line-color': this.fillColor,
					'line-width': this.contourLineWidth,
				},
				layout: {
					'line-cap': 'round',
					'line-join': 'round',
				},
			})

			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
