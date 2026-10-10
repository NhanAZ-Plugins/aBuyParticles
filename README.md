# aBuyParticles

This repository contains the historical `aParticles` plugin, which offered player particle effects through an economy plugin. The manifest credits Ghast Noob as translator.

## Compatibility and status

`plugin.yml` declares PocketMine-MP API `2.0.0`. The source uses old player, level, particle and scheduler APIs. It has not been ported to Axolotl-PM 5. Do not install it on a current server or use it with real player balances.

## Historical configuration and risks

`resources/config.yml` lists particle prices and Vietnamese messages. The source expects a `period` value that is absent from that file. It also asks for a message key named `necesita comprar`, while the configuration uses `needToBuy`. The economy adapter may be `null` even though the purchase path calls it, and the purchase path grants a particle without checking whether the charge was persisted. A port needs validated configuration, a declared economy dependency and failure tests before enabling purchases.

The original source and translation attribution remain intact. There is no LICENSE file, CI workflow, DevTools PHAR, Axolotl-PM 5 runtime test or verified release. Resolve redistribution rights with the rights holders before publishing a modified build.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
