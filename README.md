# aBuyParticles

This repository contains the historical `aParticles` plugin, which offered player particle effects through an economy plugin. The manifest credits Ghast Noob as translator.

## Compatibility and status

`plugin.yml` declares PocketMine-MP API `2.0.0`. The source uses old player, level, particle and scheduler APIs. It has not been ported to Axolotl-PM 5. Do not install it on a current server or use it with real player balances.

## Historical configuration and risks

`resources/config.yml` lists particle prices and Vietnamese messages. The current source now supplies a safe five-second scheduler fallback, uses the configured `needToBuy` key, rejects purchases without a supported economy adapter, verifies a charge before granting a paid particle, prevents duplicate purchases and saves the updated unlock list immediately. The legacy API and particle implementation still need a supported port and server-level tests before this plugin is used on a current server.

The translator attribution remains intact; no license was added. There is no CI workflow, DevTools PHAR, Axolotl-PM 5 runtime test or verified release. The added economy fixture and local lint do not establish compatibility or distribution rights. Resolve source provenance before publishing a modified build.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
