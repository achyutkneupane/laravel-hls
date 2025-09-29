# Changelog

## v0.15 - 2025-09-29

### v0.15.0 - 2025-09-30

#### 🎉 New Features

- Adds events which are dispatched for success and failure cases by @achyutkneupane in https://github.com/achyutkneupane/laravel-hls/pull/29

## v0.14.1 - 2025-09-27

- no changes

## v0.14 - 2025-09-27

- no changes

## v0.13.4 - 2025-08-08

### 🧹 Chores

- chore: `tmp` folder deleted (#22) by @achyutkneupane
- chore: SonarQube issues fixed (#23) by @achyutkneupane
- chore: No implementation comments added (#24) by @achyutkneupane

## v0.13.3 - 2025-08-08

### 🏗️ Build System

- ci: Test Coverage and manual release added (#19) by @achyutkneupane
- ci: Versions and action for ffmpeg added in workflow (#21) by @achyutkneupane

### 🔍 Tests

- test: Tests for HLS conversion added (#20) by @achyutkneupane

## v0.13.2 - 2025-08-08

### 🐛 Bug Fixes

- fix: Segment response type fixed (#16) by @achyutkneupane

## v0.13.1 - 2025-08-03

### 🐛 Bug Fixes

- fix: Segment response type fixed (#16) by @achyutkneupane

## v0.13.0 - 2025-07-31

### 🎉 New Features

- feat: Added ability to specify paths for laravel ffmpeg (#15) by @webdevhayes

## v0.12.1 - 2025-07-30

### 🐛 Bug Fixes

- fix: Fix cloud pathing (#14) by @webdevhayes

## v0.12.0 - 2025-07-30

### 🎉 New Features

- feat: Service based route logic for disabling/enabling pre-built endpoints (#13) by @achyutkneupane

## v0.11.1 - 2025-07-30

### 🐛 Bug Fixes

- fix: Fixed clean up of temp files not being called (#12) by @webdevhayes

## v0.11.0 - 2025-07-30

### 🎉 New Features

- feat: Estimated time is shown in progress bar hint (#11) by @achyutkneupane

## v0.10.1 - 2025-07-25

### 📚 Documentation Changes

- docs: Badges added in README (#10) by @achyutkneupane

## v0.10.0 - 2025-07-24

### 🎉 New Features

- feat: Added option to keep/delete the original file (#8) by @webdevhayes

## v0.9.3 - 2025-07-24

### 🏗️ Build System

- ci: New package used for changelog (#9) by @achyutkneupane

## v0.9.2 - 2025-07-18

### [0.9.2](https://github.com/achyutkneupane/laravel-hls/compare/v0.9.1...v0.9.2) (2025-07-18)

### Code Refactoring

* Add string for UUID/Ulids ([#7](https://github.com/achyutkneupane/laravel-hls/issues/7)) ([a042cae](https://github.com/achyutkneupane/laravel-hls/commit/a042cae765969789a2e24f1a3e46406cee78c323))

## v0.9.1 - 2025-07-11

### [0.9.1](https://github.com/achyutkneupane/laravel-hls/compare/v0.9.0...v0.9.1) (2025-07-11)

## v0.9.0 - 2025-07-08

### [0.9.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.8.0...v0.9.0) (2025-07-08)

#### Features

* Bitrates changed for optimized contents ([f9155bd](https://github.com/achyutkneupane/laravel-hls/commit/f9155bdeef016e561d985884ded1c22f62faa61c))

## v0.8.0 - 2025-07-08

### [0.8.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.7.1...v0.8.0) (2025-07-08)

#### Features

* Encryption is done if enable_encryption is set true ([ffabe0e](https://github.com/achyutkneupane/laravel-hls/commit/ffabe0e9799cae37a996928cacc84d1700c2c5c3))
* Optional encryption from config ([#6](https://github.com/achyutkneupane/laravel-hls/issues/6)) ([c7a6936](https://github.com/achyutkneupane/laravel-hls/commit/c7a693666709492c2627b4615dc5f079f112e914))

#### Documentation

* enable_encryption added in README ([3e39d3e](https://github.com/achyutkneupane/laravel-hls/commit/3e39d3e50617fd8fdf61a478d2ed71f254498c2e))

## v0.7.1 - 2025-07-07

### [0.7.1](https://github.com/achyutkneupane/laravel-hls/compare/v0.7.0...v0.7.1) (2025-07-07)

### Documentation

* Resolution and Bitrate added in docs ([3b9c044](https://github.com/achyutkneupane/laravel-hls/commit/3b9c0446762009733f9ea7cf0fbc6cef0d69c3f0))

## v0.7.0 - 2025-07-07

### [0.7.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.6.1...v0.7.0) (2025-07-07)

#### Features

* Bitrates added in config file ([4ff8d2e](https://github.com/achyutkneupane/laravel-hls/commit/4ff8d2e620ad508766bf65a648ffcb7b861332cc))
* Resolution and Bitrate from config ([#5](https://github.com/achyutkneupane/laravel-hls/issues/5)) ([214ce01](https://github.com/achyutkneupane/laravel-hls/commit/214ce01fc2b91e13cabb5d9c3e8414d565bee0cc))
* truncate and scale resolution ([1196177](https://github.com/achyutkneupane/laravel-hls/commit/1196177ed96c856cb624ec09da2528d3ddf67614))

## v0.6.1 - 2025-07-06

### [0.6.1](https://github.com/achyutkneupane/laravel-hls/compare/v0.6.0...v0.6.1) (2025-07-06)

### Bug Fixes

* Queue name set on observer ([eb3a01b](https://github.com/achyutkneupane/laravel-hls/commit/eb3a01bcb41fea7991e7a845a0bc124bd562043e))

## v0.6.0 - 2025-07-06

### [0.6.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.5.0...v0.6.0) (2025-07-06)

#### Features

* Queue name can be changed from config ([3d9f475](https://github.com/achyutkneupane/laravel-hls/commit/3d9f475b0e490582ccd38f36479fe24b524d0283))

## v0.5.0 - 2025-07-06

### [0.5.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.4.0...v0.5.0) (2025-07-06)

#### Features

* Middleware added in routes ([37b4183](https://github.com/achyutkneupane/laravel-hls/commit/37b4183e5bee04eb4903d8097bbafea3055bcce4))

## v0.4.0 - 2025-07-06

### [0.4.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.3.0...v0.4.0) (2025-07-06)

#### Features

* HLSModel interface created ([76f3796](https://github.com/achyutkneupane/laravel-hls/commit/76f3796a1b1d678e610161cbd922e524011028ca))

#### Bug Fixes

* Process of model binding in routes updated ([#4](https://github.com/achyutkneupane/laravel-hls/issues/4)) ([a30a23e](https://github.com/achyutkneupane/laravel-hls/commit/a30a23e1c3442a0c4e8981da2811d8d857f24480))
* string-wise model binding for routes ([5869f73](https://github.com/achyutkneupane/laravel-hls/commit/5869f731089ca3a6946a9f14aa15033fdeb9fe8b))

#### Documentation

* Necessary documantation changes ([667b65f](https://github.com/achyutkneupane/laravel-hls/commit/667b65f60afe6dad4edf412ae2be8e232889991b))

## v0.3.0 - 2025-07-06

### [0.3.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.2.0...v0.3.0) (2025-07-06)

#### Features

* Auto discovery for routes ([0f86780](https://github.com/achyutkneupane/laravel-hls/commit/0f867805fdb6e86cc9cb4af90e4ffadd87a0209e))

## v0.2.0 - 2025-07-06

### [0.2.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.1.2...v0.2.0) (2025-07-06)

#### Features

* Controller added to handle the playlist, segments, and secrets ([3bcf3e5](https://github.com/achyutkneupane/laravel-hls/commit/3bcf3e5e36a73a16ed9856f6d169b80c2583f23d))
* Controller and route endpoints for accessing HLS playlist ([#3](https://github.com/achyutkneupane/laravel-hls/issues/3)) ([6dd2eee](https://github.com/achyutkneupane/laravel-hls/commit/6dd2eeedff28592e987886663a0d2e8d58fc1f16))
* Routes added to get playlist, segments, and secrets ([6c594d8](https://github.com/achyutkneupane/laravel-hls/commit/6c594d86f8426bdf7380bd7cee9559122b8268e5))

#### Documentation

* Docs added for accessing m3u8 playlist ([8fb00de](https://github.com/achyutkneupane/laravel-hls/commit/8fb00def6d28e0b04e15c4e07504a402da60ad24))

## v0.1.2 - 2025-07-05

### [0.1.2](https://github.com/achyutkneupane/laravel-hls/compare/v0.1.1...v0.1.2) (2025-07-05)

### Documentation

* link in license ([23a12b7](https://github.com/achyutkneupane/laravel-hls/commit/23a12b7f255cca59cc23c735e4db8faa9d86ae27))

## v0.1.1 - 2025-07-05

### [0.1.1](https://github.com/achyutkneupane/laravel-hls/compare/v0.1.0...v0.1.1) (2025-07-05)

### Documentation

* Basic documentation added for basic use case ([#2](https://github.com/achyutkneupane/laravel-hls/issues/2)) ([553fd7f](https://github.com/achyutkneupane/laravel-hls/commit/553fd7f92baf9ffeadb1d6ff9a359d1303659178))
* Configuration section added ([5a5563f](https://github.com/achyutkneupane/laravel-hls/commit/5a5563fb6cbab72f50b34ca6c47247042eac292f))
* Features and other sections in documentation ([2482e72](https://github.com/achyutkneupane/laravel-hls/commit/2482e728b4ba9b8b68613d81314fa075fdd1a90a))
* Installation documentation added ([71814d1](https://github.com/achyutkneupane/laravel-hls/commit/71814d130278fec1eb1870b120d8c23d82f53c02))
* Usage section added with trait use ([0c9d68a](https://github.com/achyutkneupane/laravel-hls/commit/0c9d68a74ee333ca6ae17bc943073b66e52dbfe7))

## v0.1.0 - 2025-07-05

### [0.1.0](https://github.com/achyutkneupane/laravel-hls/compare/v0.0.1...v0.1.0) (2025-07-05)

#### Features

* Classes to convert the video to HLS with config ([#1](https://github.com/achyutkneupane/laravel-hls/issues/1)) ([d74a433](https://github.com/achyutkneupane/laravel-hls/commit/d74a4330ec8c9dac703752e4ecb6d1dc6102ab88))
* Configuration defined for variables ([68ebb9f](https://github.com/achyutkneupane/laravel-hls/commit/68ebb9f545e9a358bacd0d861521f2a9f516469d))
* Conversion job checks for column before starting ([a2233a9](https://github.com/achyutkneupane/laravel-hls/commit/a2233a9a72a304196b2ffa31f1fcfc53a9e54c8b))
* Job for Converting and updating database cloned ([548ce73](https://github.com/achyutkneupane/laravel-hls/commit/548ce73b5b7d3a98f262ec5f9397d2b38dbafba1))
* Job for Updating conversion progress cloned ([a40dd30](https://github.com/achyutkneupane/laravel-hls/commit/a40dd30c162221ae493b509ece09d93ac78b2280))
* Observer added in Trait ([c0cd531](https://github.com/achyutkneupane/laravel-hls/commit/c0cd531f3d5546f8e157c9efa60a23d6e1b5f221))
* Observer to observe the created and updated events ([766d25c](https://github.com/achyutkneupane/laravel-hls/commit/766d25caf39643dd3ca70833e638764f9e095b3b))
* Trait methods to get the config variables ([d427a2b](https://github.com/achyutkneupane/laravel-hls/commit/d427a2b9f851f718e2f017bd38fc57cf49dcd157))

#### Bug Fixes

* config checking logic updated ([b138260](https://github.com/achyutkneupane/laravel-hls/commit/b138260bfec97b9e8e39e1895775e97781b1a17a))
* **test:** TestCase failed fixed ([43b10e5](https://github.com/achyutkneupane/laravel-hls/commit/43b10e57ef4ac1d324dd5104a319b43d564627ad))

#### Continuous Integration

* Extensions installed in workflow ([6ec1ba5](https://github.com/achyutkneupane/laravel-hls/commit/6ec1ba5b52339be6880088620d1a8a1174ff680a))

#### Code Refactoring

* All the static variables replaced by config variables ([77e0031](https://github.com/achyutkneupane/laravel-hls/commit/77e0031ce74b7fcb73d15af4b4b8c9b928186fc1))

#### Styles

* Rector ([e3c3eb9](https://github.com/achyutkneupane/laravel-hls/commit/e3c3eb95bd284fb5e669e862aa14237c9d20f26c))
* Rector replacement ([3f31e2f](https://github.com/achyutkneupane/laravel-hls/commit/3f31e2f0bf2ab55be55fa02d1642f4aa22abc865))

## v0.0.1 - 2025-07-05

### [0.0.1](https://github.com/achyutkneupane/laravel-hls/compare/v0.0.0...v0.0.1) (2025-07-05)
