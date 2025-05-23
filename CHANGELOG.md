# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.1.0 – 2025-05-23

### Added

- Add support for osmand.net/map/navigate routing links in reference widget
- Add osmand as direction result link type in direction picker
- Add support for organic maps location links like https://omaps.app/IyqbLiFkiD or https://omaps.app/IyqbLiFkiD/Etang_de_Thau @julien-nc [#15](https://github.com/nextcloud/integration_openstreetmap/pull/15)
- Use the globe projection that was introduced in MapLibre

### Changed

- Update npm pkgs
- Add origin header to tile proxy requests to the maptiler API keys can be restricted to NC's domain
- Switch to IAppConfig, declare api key as sensitive @julien-nc [#11](https://github.com/nextcloud/integration_openstreetmap/pull/11)
- Disable interactive widget switch
- Switch to Psalm 6 @julien-nc [#16](https://github.com/nextcloud/integration_openstreetmap/pull/16)

### Fixed

- Fix sketchy content-type in proxy

## 2.0.1 – 2024-09-05

### Added

- Password confirmation in the admin settings
- Link preview support for routing/direction links (OSRM, GraphHopper, OpenStreetMap, google, waze)
- New smart picker provider for routing/directions

### Changed

- Max NC version is now 31
- Update OSM raster tile server URLs
- Bring back vector MapTiler OSM tile server (it now works)
- Proxy vector tiles via the server

## 2.0.0 – 2024-07-21

### Added

- Watercolor tile server

### Changed

- Use latest nextcloud/vue library
- Set min and max NC version to 30

## 1.0.12 – 2024-03-04

### Added

- code style and static analysis checks

### Changed

- update npm pkgs
- use Maplibre-gl 4

### Fixed

- fix initial link type

## 1.0.11 – 2023-12-13

### Fixed

- bug when no last map state

## 1.0.10 – 2023-11-30

### Changed

- optionally proxy osm tiles through the server

### Fixed

- avoid breaking the style with nc/vue v7

## 1.0.9 – 2023-11-23

### Changed

- reset pitch when flying on the map to a search result
- keep map style in link generated with pure search (not from map)
- save map style when submitting a search result
- proxy nominatim search through the server
- update maplibre to 3.6.2

### Fixed

- style of map search suggestions

## 1.0.8 – 2023-11-21

### Fixed

- default result link type value

## 1.0.7 – 2023-11-21

### Added

- support for osmand link with marker
- new picker option to choose link type (OSM, OsmAnd, Google)

### Changed

- use @nextcloud/vue 8
- update MapLibre

### Fixed

- adjust Nominatim search URL that was deprecated
- fix picker modal height for 28

## 1.0.6 – 2023-06-28

### Changed

- update maplibre-gl which brings better performance and fixes glitches and zoom issues when 3D terrain is on
- remove OSM vector (broken)
- add WaterColor raster

### Fixed

- terrain management (when switching layers or toggling terrain)

## 1.0.5 – 2023-04-26

### Changed

- add padding to the picker component as the one of the picker will be removed @julien-nc
- update npm pkgs @julien-nc
- get rid of `@nextcloud/vue-richtext` dependency as the build bug has been fixed in nextcloud webpack config @julien-nc
- get rid of Mapbox dependencies and use their Maplibre equivalent, only use Nominatim for in-map search

## 1.0.4 – 2023-04-12
### Added
- admin switch to toggle the unified search provider

### Fixed
- search toggle being ignored
- allow searching in the smart picker even if the unified search provider is disabled

## 1.0.3 – 2023-03-20
### Changed
- include pitch, bearing, style and useTerrain in generated links, use those values in the widget's map

### Fixed
- marker not shown when picker map is loading in some cases (rare race conditions)

## 1.0.2 – 2023-03-03
### Changed
- use richtext stuff from @nextcloud/vue 7.8.0

## 1.0.1 – 2023-02-22
### Changed
- lazy load reference stuff

## 1.0.0 – 2022-12-19
### Added
* the app
