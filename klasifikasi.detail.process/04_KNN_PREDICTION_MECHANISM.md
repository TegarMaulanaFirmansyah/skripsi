# 4. KNN PREDICTION MECHANISM & WEIGHTED VOTING

## K-Nearest Neighbors Concept

### Dasar Prinsip

**Intuitif:** "Jika tetangga Anda balik-balik positif, Anda kemungkinan besar positif juga."

```
Text space (2D visualization):

            ╔═══════════════════════╗
            ║ POSITIF REGION        ║
  ● + + + + ║  ● positif samples    ║
  + ●   ●   ║  ? = test sample      ║
  ?   + +   ║  + = strong neighbor  ║
  + +   ●   ║                       ║
            ╚═══════════════════════╝

If ? is surrounded by +, likely POSITIF
```

### K=5 Alasan

```
k=1: Too noisy, outliers dominant
  Jika 1 training sample outlier dengan label salah
  → prediction jadi salah

k=3: Better, but still risky
  Vote: 2 positif, 1 negatif
  → terlalu dekat

k=5: Goldilocks zone ✅
  Vote: 4 positif, 1 negatif atau 3 positif, 2 negatif
  → lebih robust, less overtfit

k=10+: Too slow, lose locality
  Tetangga yang jauh mulai diperhitungkan
  → predictions kurang akurat
```

---

## Algorithm: Prediction untuk 1 Sample

### Input:
```
testVector = [1, 2, 1, 0]  // vectorized test text
trainingVectors = [        // all training text vectors
  [5, 3, 2, 1],  // label: positif
  [0, 1, 4, 3],  // label: negatif
  [2, 1, 1, 4],  // label: netral
  ...
]
trainingData = [           // training metadata
  {label: "positif"},
  {label: "negatif"},
  {label: "netral"},
  ...
]
```

### Process (5 Steps)

```
╔════════════════════════════════════╗
║ STEP 1: COMPUTE MAGNITUDES         ║
╚════════════════════════════════════╝
    testMagnitude = vectorMagnitude(testVector)
    trainMagnitudes[] = compute untuk semua training
    ↓
╔════════════════════════════════════╗
║ STEP 2: CALCULATE SIMILARITIES     ║
╚════════════════════════════════════╝
    FOR each training vector:
      similarity = cosineSimilarityFast(...)
      Store {index, similarity, label}
    ↓
╔════════════════════════════════════╗
║ STEP 3: SORT BY SIMILARITY         ║
╚════════════════════════════════════╝
    Sort similarities descending
    similarities[0] = highest similarity
    similarities[4] = 5th highest similarity
    ↓
╔════════════════════════════════════╗
║ STEP 4: WEIGHTED VOTING            ║
╚════════════════════════════════════╝
    Take top 5 neighbors
    Weight each vote by their similarity
    Aggregate votes per label
    ↓
╔════════════════════════════════════╗
║ STEP 5: NORMALIZE & OUTPUT         ║
╚════════════════════════════════════╝
    Normalize confidence score 0-1
    Return {label, confidence}
```

---

## Step 1: Vector Magnitude Calculation

### Formula:
```
magnitude = √(x₁² + x₂² + x₃² + ... + xₙ²)
```

### Example:
```
vector = [1, 2, 1, 0]

magnitude = √(1² + 2² + 1² + 0²)
          = √(1 + 4 + 1 + 0)
          = √6
          ≈ 2.449
```

### Implementation:
```php
private function vectorMagnitude(array $vector): float
{
    $sum = 0;
    foreach ($vector as $val) {
        $sum += $val * $val;  // accumulate squares
    }
    return sqrt($sum);        // take square root
}
```

### Optimization:
```
Pre-compute magnitudes ONCE
├─ testMagnitude = √(...)  // compute once for test vector
└─ trainMagnitudes[] computed in loop

Reuse magnitudes untuk SEMUA similarity calculations
├─ cosineSimilarityFast(v1, v2, mag1, mag2)
└─ Avoid redundant magnitude recalculation
```

---

## Step 2: Cosine Similarity

### Formula:
```
cosine_similarity = (A · B) / (||A|| × ||B||)

Dimana:
  A · B = dot product = Σ(aᵢ × bᵢ)
  ||A|| = magnitude of A = √(Σ aᵢ²)
  ||B|| = magnitude of B = √(Σ bᵢ²)

Range: [-1, 1]
  1.0  = identical vectors (perfect match)
  0.5  = somewhat similar
  0.0  = orthogonal (no similarity)
  -1.0 = opposite vectors
```

### Example Calculation:

```
testVector    = [1, 2, 1, 0]
trainVector   = [5, 3, 2, 1]

Step 1: Compute dot product
A · B = 1×5 + 2×3 + 1×2 + 0×1
      = 5 + 6 + 2 + 0
      = 13

Step 2: Compute magnitudes
||A|| = √(1² + 2² + 1² + 0²) = √6 ≈ 2.449
||B|| = √(5² + 3² + 2² + 1²) = √39 ≈ 6.245

Step 3: Divide
cosine_similarity = 13 / (2.449 × 6.245)
                  = 13 / 15.304
                  ≈ 0.849
```

### Interpretation:
```
similarity = 0.849 → Very similaranya! (85% match)
similarity = 0.5   → Somewhat similar (50% match)
similarity = 0.1   → Very different (10% match)
```

### Implementation (Optimized):
```php
private function cosineSimilarityFast(array $vectorA, array $vectorB, 
                                      float $normA, float $normB): float
{
    // Check for zero magnitude (avoid division by zero)
    if ($normA == 0 || $normB == 0) {
        return 0;
    }

    // Compute dot product
    $dotProduct = 0;
    for ($i = 0; $i < count($vectorA); $i++) {
        $dotProduct += $vectorA[$i] * $vectorB[$i];
    }

    // Return normalized similarity
    return $dotProduct / ($normA * $normB);
}
```

**Optimization Detail:**
```
Magnitudes passed as parameters (pre-computed)
├─ Avoid redundant sqrt() calls
└─ Typical improvement: 10-20% faster
```

---

## Step 3: Find Top-K Neighbors

### Data Structure:
```php
$similarities = [
    ['index' => 42, 'similarity' => 0.892, 'label' => 'positif'],
    ['index' => 7,  'similarity' => 0.849, 'label' => 'positif'],
    ['index' => 15, 'similarity' => 0.763, 'label' => 'negatif'],
    ['index' => 28, 'similarity' => 0.721, 'label' => 'netral'],
    ['index' => 99, 'similarity' => 0.715, 'label' => 'negatif'],
    // ... more entries (unsorted)
]
```

### Sorting:
```php
usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

// Result (sorted descending by similarity):
[
    ['similarity' => 0.892, 'label' => 'positif'],  // 1st neighbor
    ['similarity' => 0.849, 'label' => 'positif'],  // 2nd neighbor
    ['similarity' => 0.763, 'label' => 'negatif'],  // 3rd neighbor
    ['similarity' => 0.721, 'label' => 'netral'],   // 4th neighbor
    ['similarity' => 0.715, 'label' => 'negatif'],  // 5th neighbor
    // rest ignored
]
```

### Take Top-K:
```php
$k = min(5, count($similarities));  // k=5 atau less jika available

for ($i = 0; $i < $k; $i++) {
    $neighbor = $similarities[$i];
    // Process top-K only
}
```

---

## Step 4: Weighted Voting

### Concept:
```
Each neighbor votes, weighted by similarity

neighbor 1 (similarity 0.892): ████████▉ 0.892 votes
neighbor 2 (similarity 0.849): ████████▍ 0.849 votes
neighbor 3 (similarity 0.763): ███████▌  0.763 votes
neighbor 4 (similarity 0.721): ███████   0.721 votes
neighbor 5 (similarity 0.715): ███████   0.715 votes
```

### Aggregation:
```
Votes by label:
┌────────────────────────────────────────┐
│ Label 1: positif                       │
│   Neighbor 1: 0.892 votes              │
│   Neighbor 2: 0.849 votes              │
│   ─────────────────────────────────    │
│   Total: 1.741 votes (positif)         │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│ Label 2: negatif                       │
│   Neighbor 3: 0.763 votes              │
│   Neighbor 5: 0.715 votes              │
│   ─────────────────────────────────    │
│   Total: 1.478 votes (negatif)         │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│ Label 3: netral                        │
│   Neighbor 4: 0.721 votes              │
│   ─────────────────────────────────    │
│   Total: 0.721 votes (netral)          │
└────────────────────────────────────────┘

Total votes: 1.741 + 1.478 + 0.721 = 3.940
```

### Implementation:
```php
$labelVotes = [];
$totalWeight = 0;

for ($i = 0; $i < $k; $i++) {
    $label = $similarities[$i]['label'];
    $weight = $similarities[$i]['similarity'];
    
    // Aggregate votes
    $labelVotes[$label] = ($labelVotes[$label] ?? 0) + $weight;
    $totalWeight += $weight;
}

// Result:
// $labelVotes = [
//     'positif'  => 1.741,
//     'negatif'  => 1.478,
//     'netral'   => 0.721
// ]
// $totalWeight = 3.940
```

---

## Step 5: Normalize & Output

### Find Winner:
```php
$bestLabel = array_key_first($labelVotes);  // start with first
$bestConfidence = $labelVotes[$bestLabel] / $totalWeight;

foreach ($labelVotes as $label => $votes) {
    if ($votes > $labelVotes[$bestLabel]) {
        $bestLabel = $label;
        $bestConfidence = $votes / $totalWeight;
    }
}
```

### Confidence Score:
```
confidence = (votes for winning label) / (total votes)

Example:
votes_positif = 1.741
total_votes = 3.940
confidence = 1.741 / 3.940 = 0.441

Interpretation:
44.1% confidence in positif prediction

Why not higher?
├─ negatif juga cukup kuat (1.478)
├─ Competition antara labels
└─ Reflecting uncertainty
```

### Confidence Ranges:

```
confidence = 1.0-0.9
  │ All 5 neighbors agree on same label
  │ Overwhelming consensus
  └─ VERY HIGH confidence ✅

confidence = 0.9-0.7
  │ 4-5 neighbors agree
  │ Strong confidence
  └─ HIGH confidence ✅

confidence = 0.7-0.5
  │ 3 neighbors agree, 1-2 disagree
  │ Moderate confidence
  └─ MODERATE confidence ⚠️

confidence = 0.5-0.3
  │ Close split, uncertainty
  │ Less reliable
  └─ LOW confidence ⚠️

confidence < 0.3
  │ Highly uncertain, near random
  │ Very unreliable
  └─ VERY LOW confidence ❌
```

### Return Value:
```php
return [
    'label' => 'positif',
    'confidence' => 0.441
];
```

---

## Complete Example: 3 Different Cases

### Case 1: Confident Positive (Easy)

```
Test text: "produk bagus puas sekali"
Test vector: [1, 3, 1, 0]

Top-5 neighbors:
  1. similarity=0.92, label=positif
  2. similarity=0.91, label=positif
  3. similarity=0.88, label=positif
  4. similarity=0.87, label=positif
  5. similarity=0.85, label=positif  (all agree!)

Voting:
  positif: 0.92+0.91+0.88+0.87+0.85 = 4.43
  total: 4.43
  confidence: 4.43/4.43 = 1.00

Output: label='positif', confidence=1.0 ✅
Meaning: VERY SURE positive
```

### Case 2: Uncertain Mixed (Hard)

```
Test text: "produk cukup lumayan bagus"
Test vector: [1, 1, 1, 1]

Top-5 neighbors:
  1. similarity=0.65, label=positif
  2. similarity=0.64, label=negatif
  3. similarity=0.61, label=netral
  4. similarity=0.60, label=positif
  5. similarity=0.59, label=negatif

Voting:
  positif: 0.65+0.60 = 1.25
  negatif: 0.64+0.59 = 1.23
  netral: 0.61
  total: 3.09
  confidence (positif): 1.25/3.09 = 0.40

Output: label='positif', confidence=0.4 ⚠️
Meaning: SLIGHTLY positive, but uncertain
```

### Case 3: Clear Negative (Easy)

```
Test text: "jelek buruk tidak bagus"
Test vector: [0, 0, 2, 1]

Top-5 neighbors:
  1. similarity=0.88, label=negatif
  2. similarity=0.87, label=negatif
  3. similarity=0.86, label=negatif
  4. similarity=0.84, label=negatif
  5. similarity=0.15, label=positif  (outlier, but low sim)

Voting:
  negatif: 0.88+0.87+0.86+0.84 = 3.45
  positif: 0.15
  total: 3.60
  confidence (negatif): 3.45/3.60 = 0.96

Output: label='negatif', confidence=0.96 ✅
Meaning: VERY SURE negative
```

---

## Why Weighted Voting Works

1. **Similarity as confidence:**
   - High similarity = neighbor's vote counts more
   - Low similarity = neighbor's vote counts less

2. **Aggregation reduces noise:**
   - 1 neighbor with wrong label (outlier) doesn't matter
   - 5 neighbors: outlier diluted by majority

3. **Natural confidence:**
   - Automatic confidence score from vote distribution
   - No need for separate confidence formula

4. **Explainability:**
   - Can explain prediction with "these neighbors voted for X"
   - Easy to understand untuk dosen 👍

---

## Code Summary

```php
private function predictSVM(array $testVector, array $trainingVectors, array $trainingData): array
{
    // Compute test magnitude
    $testMagnitude = $this->vectorMagnitude($testVector);
    if ($testMagnitude == 0) {
        return ['label' => 'netral', 'confidence' => 0];
    }

    // Compute similarities to all training vectors
    $similarities = [];
    foreach ($trainingVectors as $i => $trainVector) {
        $trainMagnitude = $this->vectorMagnitude($trainVector);
        if ($trainMagnitude == 0) continue;
        
        $similarity = $this->cosineSimilarityFast($testVector, $trainVector, $testMagnitude, $trainMagnitude);
        $similarities[] = [
            'index' => $i,
            'similarity' => $similarity,
            'label' => $trainingData[$i]['label']
        ];
    }
    
    if (empty($similarities)) {
        return ['label' => 'netral', 'confidence' => 0];
    }
    
    // Sort by similarity descending
    usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    
    // Weighted voting from top 5
    $k = min(5, count($similarities));
    $labelVotes = [];
    $totalWeight = 0;
    
    for ($i = 0; $i < $k; $i++) {
        $label = $similarities[$i]['label'];
        $weight = $similarities[$i]['similarity'];
        $labelVotes[$label] = ($labelVotes[$label] ?? 0) + $weight;
        $totalWeight += $weight;
    }
    
    // Find winning label
    if (!empty($labelVotes) && $totalWeight > 0) {
        $bestLabel = array_key_first($labelVotes);
        $bestConfidence = $labelVotes[$bestLabel] / $totalWeight;
        
        foreach ($labelVotes as $label => $votes) {
            if ($votes > $labelVotes[$bestLabel]) {
                $bestLabel = $label;
                $bestConfidence = $votes / $totalWeight;
            }
        }
    }

    return [
        'label' => $bestLabel ?? 'netral',
        'confidence' => min($bestConfidence ?? 0, 1.0)
    ];
}
```

