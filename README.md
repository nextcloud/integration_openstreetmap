# OpenStreetMap integration in Nextcloud

This app adds:
* a search provider for locations using Nominatim (OpenStreetMap)
* reference providers to resolve map links. It supports:
    * OpenStreetMap
    * Google maps
    * Here maps
    * Bing maps
    * Duckduckgo maps
* a custom link picker component to generate OpenStreetMap location links
* 2 reference widgets to render map links:
    * a basic one embedding OpenStreetMap
    * a custom one showing an awesome Maplibre map with 3D terrain, embedded search, vector tiles and more...

## 🔧 Configuration

### User settings

There is a "OpenStreetMap integration" section in the "Connected accounts" user settings section to
set the user specific settings.

### Admin settings

There is a "Connected accounts" **admin** settings section to manage your API keys.
The Maptiler API key has a default value.
It is used to get vector map tiles. The default key is very limited. You can get a free API key on https://www.maptiler.com .
