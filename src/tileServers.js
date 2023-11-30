import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
const proxyOsm = loadState('integration_openstreetmap', 'proxy-osm', false)

export function getRasterTileServers(apiKey) {
	return {
		osmRaster: {
			title: 'OpenStreetMap raster',
			version: 8,
			// required to display text, apparently vector styles get this but not raster ones
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'osm-source': {
					type: 'raster',
					tiles: proxyOsm
						? [
							generateUrl('/apps/integration_openstreetmap/tiles/osm/a/') + '{x}/{y}/{z}',
							generateUrl('/apps/integration_openstreetmap/tiles/osm/b/') + '{x}/{y}/{z}',
							generateUrl('/apps/integration_openstreetmap/tiles/osm/c/') + '{x}/{y}/{z}',
						]
						: [
							'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png',
							'https://b.tile.openstreetmap.org/{z}/{x}/{y}.png',
							'https://c.tile.openstreetmap.org/{z}/{x}/{y}.png',
						],
					tileSize: 256,
					attribution: 'Map data &copy; 2013 <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
				},
			},
			layers: [
				{
					id: 'osm-layer',
					type: 'raster',
					source: 'osm-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		esriTopo: {
			title: 'ESRI topo with relief',
			version: 8,
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'esri-topo-source': {
					type: 'raster',
					tiles: proxyOsm
						? [
							generateUrl('/apps/integration_openstreetmap/tiles/esri-topo/a/') + '{x}/{y}/{z}',
						]
						: [
							'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
						],
					tileSize: 256,
					attribution: 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ, '
						+ 'TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ord'
						+ 'nance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User'
						+ ' Community',
				},
			},
			layers: [
				{
					id: 'esri-topo-layer',
					type: 'raster',
					source: 'esri-topo-source',
					minzoom: 0,
					maxzoom: 19,
				},
			],
			maxzoom: 19,
		},
		// didn't find a working server
		/*
		waterColor: {
			title: 'WaterColor',
			version: 8,
			glyphs: 'https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=' + apiKey,
			sources: {
				'watercolor-source': {
					type: 'raster',
					tiles: proxyOsm
						? [
							generateUrl('/apps/integration_openstreetmap/tiles/watercolor/a/') + '{x}/{y}/{z}',
						]
						: [
							'https://tiles.stadiamaps.com/tiles/stamen_watercolor/{z}/{x}/{y}.jpg',
						],
					tileSize: 256,
					attribution: 'Map tiles by <a href="https://stamen.com">Stamen Design</a>'
						+ ', under <a href="https://creativecommons.org/license'
						+ 's/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenSt'
						+ 'reetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0"'
						+ '>CC BY SA</a>.',
				},
			},
			layers: [
				{
					id: 'watercolor-layer',
					type: 'raster',
					source: 'watercolor-source',
					minzoom: 0,
					maxzoom: 18,
				},
			],
			maxzoom: 18,
		},
		*/
	}
}

export function getVectorStyles(apiKey) {
	return {
		streets: {
			title: 'Streets',
			uri: 'https://api.maptiler.com/maps/streets/style.json?key=' + apiKey,
		},
		satellite: {
			title: 'Satellite',
			uri: 'https://api.maptiler.com/maps/hybrid/style.json?key=' + apiKey,
		},
		outdoor: {
			title: 'Outdoor',
			uri: 'https://api.maptiler.com/maps/outdoor/style.json?key=' + apiKey,
		},
		// does not work ATM
		// malformed style.json (extra space):
		// layers[107].paint.line-color.stops[0][1]: color expected, " #787878" found
		/*
		osm: {
			title: 'OpenStreetMap',
			uri: 'https://api.maptiler.com/maps/openstreetmap/style.json?key=' + apiKey,
		},
		*/
		dark: {
			title: 'Dark',
			uri: 'https://api.maptiler.com/maps/streets-dark/style.json?key=' + apiKey,
		},
	}
}
