<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author 2022 Julien Veyssier <eneiluj@posteo.net>
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
	<div class="location-reference">
		<div class="location-wrapper">
			<strong>
				{{ richObject.display_name }}
			</strong>
			<iframe
				class="location-frame"
				frameborder="0"
				scrolling="no"
				marginheight="0"
				marginwidth="0"
				:src="frameUrl" />
		</div>
	</div>
</template>

<script>
export default {
	name: 'LocationReferenceWidget',

	components: {
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
		}
	},

	computed: {
		frameUrl() {
			// <iframe width="425" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=4.5333194732666025%2C44.70715721664363%2C4.613656997680665%2C44.74807432587679&amp;layer=mapnik&amp;marker=44.72761938925528%2C4.573488235473633" style="border: 1px solid black"></iframe><br/><small><a href="https://www.openstreetmap.org/?mlat=44.7276&amp;mlon=4.5735#map=14/44.7276/4.5735">Afficher une carte plus grande</a></small>
			// https://www.openstreetmap.org/export/embed.html?bbox=4.5333194732666025%2C44.70715721664363%2C4.613656997680665%2C44.74807432587679&amp;layer=mapnik
			const bb = this.richObject.boundingbox
			const bbp = [bb[2], bb[0], bb[3], bb[1]].join(',')
			return 'https://www.openstreetmap.org/export/embed.html?'
				+ 'bbox=' + encodeURIComponent(bbp)
				+ '&marker=' + encodeURIComponent(this.richObject.lat + ',' + this.richObject.lon)
		},
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.location-reference {
	width: 100%;
	// padding: 12px;
	white-space: normal;

	.location-wrapper {
		width: 100%;
		display: flex;
		flex-direction: column;
		// in case there is an absolute inside
		position: relative;

		.location-frame {
			width: 100%;
			height: 350px;
		}
	}
}
</style>
