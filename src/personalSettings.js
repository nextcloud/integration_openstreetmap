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

// import Vue from 'vue'
import { createApp } from 'vue'
import PersonalSettings from './components/PersonalSettings.vue'

// Vue.mixin({ methods: { t, n } })

// const VueSettings = Vue.extend(PersonalSettings)
// new VueSettings().$mount('#osm_prefs')

const app = createApp(PersonalSettings)
app.mixin({ methods: { t, n } })
app.mount('#osm_prefs')
