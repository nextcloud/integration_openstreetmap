# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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
