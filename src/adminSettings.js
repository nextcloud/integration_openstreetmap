/**
 * Nextcloud - OpenStreetMap
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

import Vue from 'vue'
import AdminSettings from './components/AdminSettings.vue'
Vue.mixin({ methods: { t, n } })

const VueSettings = Vue.extend(AdminSettings)
new VueSettings().$mount('#osm_prefs')
