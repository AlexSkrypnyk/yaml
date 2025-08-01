# Fuzzy Comment Matching Implementation Analysis

## Current Problem
The existing Comments class uses exact line matching to associate comments with YAML content. This approach fails when YAML is parsed and re-dumped because:
- Formatting changes (quotes, spacing, escaping)
- Value transformations (numbers, booleans, null values)
- String transformations that change exact line content
- Result: Comments can't find their "home" lines anymore

## Proposed Solution: Structured Fuzzy Matching

### Core Concept
Parse each YAML line into structured components, then use fuzzy matching on normalized versions to associate comments with content lines, even when formatting changes occur.

**Key Components:**
1. **Line Parsing**: Extract level, key, value from each line
2. **Dual Normalization**: Separate algorithms for keys vs values
3. **Hierarchical Matching**: Use level information for better context
4. **Context Awareness**: Consider surrounding lines for better matching
5. **Threshold-based**: Use probabilistic scoring to determine matches
6. **Fallback Strategy**: Graceful degradation when perfect matches aren't found

### Line Structure
Each parsed line contains:
```php
[
    'level' => 0,                    // Indentation level (0, 1, 2, etc.)
    'key' => 'database',             // Key name (if exists)
    'value' => 'localhost',          // Value (if exists)  
    'normalized_key' => 'database',  // Normalized key using key algorithm
    'normalized_value' => 'localhost', // Normalized value using value algorithm
    'original' => 'database: localhost' // Original line for reference
]
```

## Why This Approach Could Work

### ✅ Handles YAML Transformations
```yaml
# Before: host: localhost
# After:  host: 'localhost'
# Normalized: "hostlocalhost" → still matches!
```
- Removes format sensitivity (quotes, spacing, escaping)
- Survives parse/dump cycles that change presentation but not meaning
- Alphanumeric-only comparison ignores YAML syntax artifacts

### ✅ Context-Aware Matching
```yaml
# Comment
key1: value1     ← Target line
key2: value2     ← Context helps confirm match
```
- Reduces false positives by considering surrounding lines
- Handles duplicate keys by using neighborhood context
- More robust than single-line matching

### ✅ Probabilistic Resilience
- Threshold-based decisions allow for imperfect matches
- Graceful degradation (some comments preserved better than none)
- Tunable accuracy vs coverage trade-off

## Challenges to Consider

### 1. Dual Normalization Strategy
```php
// Keys: More conservative normalization (preserve structure)
"host"           → "host"           // Keep alphanumeric
"api_endpoint"   → "apiendpoint"    // Remove underscores
"some-key"       → "somekey"        // Remove hyphens

// Values: Aggressive normalization (focus on content)
"'localhost'"    → "localhost"      // Remove quotes
"true"           → "true"           // Keep booleans as text
"3306"           → "3306"           // Keep numbers
"some value"     → "somevalue"      // Remove spaces
```
**Benefit**: Keys are more stable, values change more during parse/dump cycles

### 2. Multi-Layer Context Window Matching

**Context Variables:**
- `context_before` = lines above the target
- `context_after` = lines below the target  
- `position_window` = maximum allowed positional drift
- Zero at file start/end (ignored)

**Three-Layer Context Algorithm:**

**Layer 1: Positional Constraint**
```
Original line position: 15
Current candidate position: 18
Position drift: |18 - 15| = 3 lines

If drift > position_window → ❌ REJECT (too far from expected position)
If drift ≤ position_window → ✅ Continue to Layer 2
```

**Layer 2: Context Line Validation** 
```
TARGET LINE: database: localhost (at position 18)

Context Before Assessment:
- Check line-1 (position 17): Does it match context threshold? 
  - YES: ✅ Good, check line-2 (position 16)
    - line-2 matches: ✅✅ Strong context match
    - line-2 doesn't match: ✅ Still good (line-1 sufficient)
  - NO: Check line-2 (position 16) directly
    - line-2 matches: ✅ Good (line-1 was probably updated/changed) 
    - line-2 doesn't match: ❌ Outside context window

Context After Assessment: (same logic applied downward)
```

**Layer 3: Recursive Context Validation**
```
Check if context lines themselves are within THEIR expected positions:
- line-1 (expected ~position 14, actual 17): drift = 3 ≤ position_window ✅
- line-2 (expected ~position 13, actual 16): drift = 3 ≤ position_window ✅

If context lines are also positionally valid → ✅ ACCEPT
If context lines are too far from their expected positions → ❌ REJECT
```

**Key Insight**: Skip over potentially updated lines to find stable context anchors

```yaml
# Example scenario:
api_key: old_value     # line-2: matches context ✅
port: 3306            # line-1: doesn't match (was updated) ❌  
database: localhost   # TARGET: trying to match
timeout: 30           # line+1: matches context ✅
retries: 3           # line+2: matches context ✅
```
**Result**: Context window = VALID (line-2 + line+1 + line+2 provide anchoring)

### 3. Multiple Match Resolution with Hierarchy
```yaml
# Handle duplicate keys using level + context:
database:             # level 0, context: []
  host: localhost     # level 1, context: ["database"] ← Match A
  port: 3306         # level 1, context: ["database"]
api:                 # level 0, context: []  
  host: localhost    # level 1, context: ["api"] ← Match B (different context!)
```
**Strategy**: Use `level + parent_context` as discriminator for duplicate keys

### 4. Performance Considerations
- O(n×m) comparison (every content line vs every stored comment)
- String similarity algorithms can be expensive
- Context matching multiplies comparisons

## Refinement Ideas

### A. Smart Normalization
```php
function normalize($line) {
    // Keep structure markers for better matching
    $key_value = explode(':', $line, 2);
    if (count($key_value) === 2) {
        return trim(preg_replace('/[^a-zA-Z0-9]/', '', $key_value[0])) . ':' .
               trim(preg_replace('/[^a-zA-Z0-9]/', '', $key_value[1]));
    }
    return preg_replace('/[^a-zA-Z0-9]/', '', $line);
}
```

### B. Hierarchical Context
- Indentation-aware context (parent-child relationships matter more)
- Distance weighting (closer lines have higher influence)

### C. Early Exit Optimizations
- Perfect match shortcuts (if exact match found, skip fuzzy)
- Candidate pre-filtering (eliminate obviously wrong matches early)

## Overall Assessment: ✅ FEASIBLE

This approach is **significantly more promising** than the current system because:

1. **Addresses root cause**: Format-change resilience
2. **Works within constraints**: No AST needed, post-processing friendly  
3. **Handles real-world scenarios**: Parse/dump transformations, duplicate keys
4. **Tunable trade-offs**: Accuracy vs performance via thresholds
5. **Incremental improvement**: Can be implemented step-by-step

## Implementation Strategy

### Phase 1: Basic fuzzy matching with normalized strings
### Phase 2: Add context-aware scoring  
### Phase 3: Optimize performance and tune thresholds
### Phase 4: Handle edge cases and duplicate resolution

## Questions for Discussion

### Answered by Structured Approach:
1. ✅ **Normalization strategy**: Dual algorithms - conservative for keys, aggressive for values
2. ✅ **Context handling**: Hierarchical context using level + parent relationships  
3. ✅ **Multiple matches**: Use level + parent_context as discriminator
4. ✅ **Key vs value weighting**: Different normalization algorithms handle this

### Still Need Answers:
1. What's an appropriate **context threshold** for determining if a line matches context?
2. What's an appropriate **final similarity threshold** (e.g., 80%) for accepting a match?
3. What's an appropriate **position_window** size (e.g., 5-10 lines maximum drift)?
4. ✅ **String similarity algorithm**: Hybrid approach prioritizing accuracy:
   - **Primary**: Levenshtein distance (good baseline, handles insertions/deletions)
   - **Secondary**: Jaro-Winkler (better for prefix changes like quotes/spacing)
   - **Fallback**: Longest Common Subsequence (handles reordering within values)
5. How do we handle completely new content that has no original match?
6. Should we prioritize key matching over value matching, or weight them equally?
7. How do we handle list items vs object keys (different parsing rules)?
8. What's the maximum acceptable performance cost for the parsing step?

### Two-Stage Threshold System:
1. **Context Threshold**: Determines if context lines are "close enough" to be considered matches
2. **Final Threshold**: Combined score (target + context) must exceed this to accept the match (e.g., 80%)

### Accuracy-First String Similarity Strategy

**Hybrid Algorithm Approach:**
```php
function calculateSimilarity($str1, $str2) {
    // 1. Try exact match first (fastest, 100% accurate)
    if ($str1 === $str2) return 1.0;
    
    // 2. Try normalized exact match
    if (normalize($str1) === normalize($str2)) return 0.95;
    
    // 3. Calculate multiple similarity scores
    $levenshtein = 1 - (levenshtein($str1, $str2) / max(strlen($str1), strlen($str2)));
    $jaro_winkler = jaro_winkler($str1, $str2);
    $lcs = longestCommonSubsequence($str1, $str2);
    
    // 4. Return the HIGHEST score (most optimistic for accuracy)
    return max($levenshtein, $jaro_winkler, $lcs);
}
```

**Why This Maximizes Accuracy:**
- **Multiple algorithms** catch different types of changes
- **Highest score wins** reduces false negatives (missing correct matches)
- **Fast exact matches** handle unchanged content efficiently
- **Graceful degradation** from exact → normalized → fuzzy

**Algorithm Strengths:**
- **Levenshtein**: Great for `"host"` vs `"'host'"` (quote additions)
- **Jaro-Winkler**: Excellent for `"database_host"` vs `"databasehost"` (prefix similarity)  
- **LCS**: Handles `"key: value"` vs `"key:value"` (spacing changes)

## Constraints

- Cannot create AST (too complex)
- Must work as post-processing add-on
- Must handle content that changes between collect() and inject()
- Should maintain reasonable performance
- Must be tunable for different use cases