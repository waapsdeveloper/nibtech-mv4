# CPU Optimization - Phased Implementation Plan

## ✅ Phase 1: Critical Fixes (IMPLEMENTED)

**Status:** Ready to deploy and monitor

### Changes Made:

1. **`refresh:new`** - Most Critical
   - ✅ Added `withoutOverlapping()` - Prevents multiple instances
   - ✅ Changed interval from `everyTwoMinutes()` to `everyFiveMinutes()` - Reduces frequency by 60%
   - ✅ Added `onOneServer()` - Prevents conflicts in multi-server setup
   - ✅ Added `runInBackground()` - Non-blocking execution

2. **`refresh:orders`**
   - ✅ Added `withoutOverlapping()`
   - ✅ Added `onOneServer()`
   - ✅ Added `runInBackground()`

3. **`refresh:latest`**
   - ✅ Added `withoutOverlapping()`
   - ✅ Added `onOneServer()`
   - ✅ Added `runInBackground()`

4. **`refurbed:new`**
   - ✅ Added `withoutOverlapping()`
   - ✅ Added `onOneServer()`
   - ✅ Added `runInBackground()`

5. **`functions:ten`**
   - ✅ Added `withoutOverlapping()`
   - ✅ Added `onOneServer()`
   - ✅ Added `runInBackground()`

6. **`api-request:process`**
   - ✅ Added `withoutOverlapping()`
   - ✅ Added `onOneServer()`
   - ✅ Added `runInBackground()`

### Expected Impact:
- **CPU Reduction:** 30-40% immediately
- **Eliminates:** Overlapping command execution
- **Improves:** System stability and predictability

### Monitoring Plan:
1. **Before Deployment:**
   - Note current CPU baseline (check Digital Ocean dashboard)
   - Note peak CPU times

2. **After Deployment (First 24 hours):**
   - Monitor CPU usage every 2-4 hours
   - Check for any command failures
   - Verify commands are completing successfully
   - Check Digital Ocean CPU graph

3. **After 24-48 hours:**
   - Compare CPU graphs (before vs after)
   - Check if CPU peaks are reduced
   - Verify no functionality is broken
   - If successful, proceed to Phase 2

### Rollback Plan:
If issues occur, revert `app/Console/Kernel.php` to previous version.

---

## 🔄 Phase 2: High Priority Optimizations (Next)

**Status:** Pending Phase 1 validation

### Planned Changes:

1. **Optimize `price:handler`**
   - Implement chunking (process 50-100 listings at a time)
   - Add progress tracking
   - Consider queue jobs for heavy processing

2. **Optimize `functions:thirty`**
   - Add pagination for API calls
   - Implement bulk database operations
   - Cache country/currency lookups

3. **Add overlap protection to remaining commands:**
   - `functions:thirty`
   - `refurbed:orders`
   - `backup:email`
   - `functions:daily`
   - `fetch:exchange-rates`

### Expected Impact:
- **Additional CPU Reduction:** 20-30%
- **Total Reduction:** 50-60% from baseline

---

## 🔄 Phase 3: Medium Priority (Future)

### Planned Changes:

1. **Database Query Optimization**
   - Add eager loading to reduce N+1 queries
   - Add database indexes
   - Implement query caching

2. **API Call Optimization**
   - Implement rate limiting
   - Add connection pooling
   - Implement request queuing

3. **Memory Optimization**
   - Implement chunking for large datasets
   - Use cursors for large queries
   - Add memory limits

### Expected Impact:
- **Additional CPU Reduction:** 10-20%
- **Total Reduction:** 60-70% from baseline

---

## 📊 Monitoring Checklist

### Daily Checks (First Week):
- [ ] CPU usage graph (Digital Ocean dashboard)
- [ ] Command execution logs
- [ ] Any error messages
- [ ] System response times
- [ ] Memory usage

### Weekly Review:
- [ ] Compare CPU graphs (before/after)
- [ ] Review command execution times
- [ ] Check for any failed commands
- [ ] Verify business functionality still works

---

## 🚨 Warning Signs to Watch For

If you see any of these, consider rolling back:

1. **Commands failing to execute** - Check Laravel logs
2. **Orders not syncing** - Verify `refresh:new` and `refresh:orders` are working
3. **Price updates not happening** - Check `price:handler` execution
4. **CPU still high** - May need Phase 2 optimizations sooner
5. **Memory errors** - May need to adjust chunk sizes

---

## 📝 Notes

- **Deployment Time:** Best to deploy during low-traffic hours
- **Testing:** Commands will continue to run, just with overlap protection
- **No Data Loss:** These changes only affect scheduling, not data processing
- **Gradual Approach:** We're fixing the most critical issues first, then optimizing further

---

**Last Updated:** January 2026  
**Next Review:** After Phase 1 monitoring period (48 hours)

