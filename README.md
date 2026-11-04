# aBuyParticles

Particle shop for Axolotl-PM API 5 servers with a supported economy plugin.

[![CI](https://github.com/NhanAZ-Plugins/aBuyParticles/actions/workflows/build.yml/badge.svg)](https://github.com/NhanAZ-Plugins/aBuyParticles/actions/workflows/build.yml)
[![Latest release](https://img.shields.io/github/v/release/NhanAZ-Plugins/aBuyParticles)](https://github.com/NhanAZ-Plugins/aBuyParticles/releases/latest)
[![Discord](https://img.shields.io/discord/986553214889517088?label=Discord&logo=discord&logoColor=white&color=5865F2)](https://discord.gg/j2X83ujT6c)


## Compatibility

The manifest minimum is Axolotl-PM API 5.0.0. The [build workflow](https://github.com/NhanAZ-Plugins/aBuyParticles/actions/workflows/build.yml) checks exact API 5 source revisions with PHPStan at maximum level, builds and inspects the plugin PHAR, and smoke-tests startup on an isolated Axolotl-PM server. Those checks do not claim compatibility with every API 5.x release or verify connected-client particle rendering.

The plugin recognizes EconomyAPI, PocketMoney and MassiveEconomy when their expected balance methods are available. EconomyAPI purchases require its reduceMoney() success result; other supported providers must report the updated balance after setMoney(). If a charge cannot be confirmed, the particle is not unlocked.

## Data and configuration

Particle prices and messages remain in [resources/config.yml](resources/config.yml). Existing lowercase player keys and unlocked particle lists remain in the plugin data file users.yml. New-player defaults and purchase changes are saved immediately; a periodic save remains as a backup.

## Verification and provenance

The workflow tests confirmed and rejected economy transactions, PHPStan at maximum level, API 5 PHAR builds and an isolated server startup. It does not test a connected player or a real economy account. The repository has no LICENSE file; existing attribution and source history are preserved.


## Credits

Authors: NhanAZ.
