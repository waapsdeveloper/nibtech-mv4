# Console commands and Kernel analysis

**Focus:** Whether V2 commands override the client-critical commands **refresh:new**, **refresh:orders**, and **functions:thirty**.  
**Conclusion:** They do **not** override by name. All three still run the original (V1) command classes. V2 runs in parallel with different command names and can touch the same data.

**Update:** V2 order sync (`v2:sync-orders`) has been **disabled in the schedule** so V1 remains the single source of truth for orders. The four scheduled `v2:sync-orders` entries are commented out in `app/Console/Kernel.php`. V2 marketplace **stock** sync (e.g. `v2:marketplace:sync-stock-bulk`) is still scheduled.

---

## 1. How commands are registered

**Kernel.php**

- **`$commands` array:** Only two classes are listed explicitly:
  - `SupportSyncCommand::class`
  - `BMProSyncOrders::class`
- **`commands()` method:** Calls `$this->load(__DIR__.'/Commands')`, which recursively loads every `*Command.php` under `app/Console/Commands/` (including `Commands/V2/`). So all commands in `Commands/` and `Commands/V2/` are auto-discovered and registered by Laravel.
- **routes/console.php:** Only defines the `inspire` closure; it does not register any of the Artisan commands in question.

So **refresh:new**, **refresh:orders**, and **functions:thirty** are registered only via the `load(__DIR__.'/Commands')` discovery, not overridden by the Kernel or console routes.

---

## 2. Signature uniqueness (no name override)

Each of the client focus commands has a **unique** signature and a **single** implementing class:

| Signature         | Class (file)                          | Location     |
|-------------------|----------------------------------------|-------------|
| `refresh:new`     | `RefreshNew`                           | Commands/   |
| `refresh:orders`  | `RefreshOrders`                        | Commands/   |
| `functions:thirty`| `FunctionsThirty`                     | Commands/   |

**V2 commands** use **different** signatures; none use the above names:

| Signature                             | Class                      | Location   |
|---------------------------------------|----------------------------|------------|
| `v2:sync-orders`                      | `SyncMarketplaceOrders`     | Commands/V2/ |
| `v2:marketplace:sync-stock`          | `SyncMarketplaceStock`     | Commands/V2/ |
| `v2:marketplace:sync-stock-bulk`     | `SyncMarketplaceStockBulk` | Commands/V2/ |
| `v2:sync-all-marketplace-stock-from-api` | …                       | Commands/V2/ |
| `listing:stock-mismatch-report`      | `StockMismatchReport`       | Commands/V2/ |

So when the scheduler (or anyone) runs `refresh:new`, `refresh:orders`, or `functions:thirty`, Laravel will **always** execute the original V1 command. **V2 does not override these by command name.**

---

## 3. Kernel schedule (client focus + V2)

**V1 commands (client focus)** — all still scheduled:

- **refresh:new**  
  - Cron: `*/2 * * * *` (every 2 minutes)  
  - Runs: `App\Console\Commands\RefreshNew`

- **refresh:orders**  
  - Cron: `3,8,13,18,23,28,33,38,43,48,53,58 * * * *` (every 5 minutes at :03, :08, …)  
  - Runs: `App\Console\Commands\RefreshOrders`

- **functions:thirty**  
  - Hourly, via job: `ExecuteArtisanCommandJob('functions:thirty', [])` on queue `listings-sync`  
  - Runs: `App\Console\Commands\FunctionsThirty`

**V2 commands** — run in addition, not instead:

- **v2:sync-orders --type=new**  
  - Every 2 hours, between 06:00 and 22:00  
- **v2:sync-orders --type=modified**  
  - Daily at 02:00  
- **v2:sync-orders --type=care**  
  - Daily at 04:00  
- **v2:sync-orders --type=incomplete**  
  - Every 4 hours  
- **v2:marketplace:sync-stock-bulk --marketplace=1**  
  - Every 6 hours at 00:00  
- **v2:marketplace:sync-stock** (e.g. Refurbed, other marketplaces)  
  - Every 6 hours at 03:00 / 06:00  

So the scheduler runs **both** the V1 focus commands and the V2 sync commands. There is **no** replacement of the former by the latter at the schedule level.

---

## 4. “Override” in practice (data / intent)

- **SyncMarketplaceOrders** (V2) docblock states it “Replaces RefreshLatest, RefreshNew, and RefreshOrders commands” **in intent**, but it does **not** replace them in code or in the Kernel:
  - It uses the name **v2:sync-orders**, not refresh:new/refresh:orders.
  - refresh:new and refresh:orders remain scheduled and run their original logic.

- Because **both** V1 and V2 order sync run:
  - **refresh:new** (every 2 min) and **v2:sync-orders --type=new** (every 2 hours) can both update orders.
  - **refresh:orders** (every 5 min) and **v2:sync-orders --type=modified** (daily) can both update orders.
  So the only “override” risk is **data/behaviour**: both paths can write to the same orders/data; which result “wins” depends on timing and implementation, not on one command name replacing another.

- **functions:thirty** is only scheduled as `functions:thirty` (V1), and there is no V2 command with that name. So listings sync is not overridden by any V2 command name.

---

## 5. Summary

| Question | Answer |
|----------|--------|
| Do V2 commands override **refresh:new**, **refresh:orders**, or **functions:thirty** by name? | **No.** Each of those names is implemented by a single, V1 command class. |
| Does the Kernel or console routes replace these with V2? | **No.** Kernel only loads `Commands/` (and subdirs); no duplicate signatures; console.php doesn’t register these. |
| Are the three focus commands still scheduled? | **Yes.** refresh:new (every 2 min), refresh:orders (every 5 min), functions:thirty (hourly, queued). |
| Can V2 “override” behaviour? | Only in a **data** sense: v2:sync-orders and the refresh:* commands can both run and both update orders, so logic and schedule design should be aligned if you want a single source of truth. |

**Recommendation:** If the client wants only one sync path for orders, decide whether that is V1 (refresh:new / refresh:orders) or V2 (v2:sync-orders) and then either disable or reschedule the other in the Kernel so they don’t both run and contend. The analysis above confirms that disabling or changing the schedule is a configuration/schedule change, not a “V2 overrides V1 command names” issue.
