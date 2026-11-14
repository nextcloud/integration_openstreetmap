/**
 * Default geocoder, querying Nominatim directly from the browser.
 * It can be overridden in MaplibreMap with the `geocoderOptions` prop,
 * for example to use a proxied geocoder.
 * https://nominatim.org/release-docs/latest/api/Search/
 */

const NOMINATIM_SEARCH_URL = 'https://nominatim.openstreetmap.org/search'

/**
 * @param {object} config the config object provided by MaplibreGeocoder
 * @param {string} config.query the search query
 * @param {number} config.limit max number of results
 * @return {Promise<{features: object[]}>} results in Carmen GeoJSON format
 */
export async function forwardGeocodeNominatim(config) {
	const features = []
	try {
		const params = new URLSearchParams({
			q: config.query,
			format: 'json',
			addressdetails: '1',
			limit: config.limit ?? 5,
		})
		const response = await fetch(NOMINATIM_SEARCH_URL + '?' + params.toString())
		const results = await response.json()
		for (const result of results) {
			const center = [parseFloat(result.lon), parseFloat(result.lat)]
			const bb = result.boundingbox
			features.push({
				type: 'Feature',
				geometry: {
					type: 'Point',
					coordinates: center,
				},
				place_name: result.display_name,
				properties: result,
				text: result.display_name,
				place_type: ['place'],
				center,
				bbox: [bb[2], bb[0], bb[3], bb[1]],
			})
		}
	} catch (e) {
		console.error(`Failed to forwardGeocode with error: ${e}`)
	}

	return {
		features,
	}
}
