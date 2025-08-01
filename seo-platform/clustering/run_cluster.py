import sys
import json
import os
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans
from sklearn.metrics.pairwise import cosine_similarity

# Read keywords to cluster from stdin
keywords = [line.strip() for line in sys.stdin if line.strip()]
existing = json.loads(os.environ.get("EXISTING", "[]"))

if not keywords and not existing:
    print(json.dumps({"error": "No keywords provided."}))
    sys.exit(1)

# Fit TF-IDF on all keywords so vector space is shared
all_keywords = [kw for cluster in existing for kw in cluster] + keywords
vectorizer = TfidfVectorizer()
vectorizer.fit(all_keywords)

# Build centroid vectors for existing clusters
assigned = [cluster[:] for cluster in existing]
centroids = []
for cluster in existing:
    vec = vectorizer.transform(cluster)
    centroids.append(vec.mean(axis=0))

# Attach each new keyword to the best existing cluster if similar
unassigned = []
for kw in keywords:
    vec = vectorizer.transform([kw])
    if centroids:
        sims = [cosine_similarity(vec, c)[0][0] for c in centroids]
        best_idx, best_sim = max(enumerate(sims), key=lambda x: x[1])
        if best_sim >= 0.3:
            assigned[best_idx].append(kw)
            continue
    unassigned.append(kw)

unclustered_out = []

# Cluster any remaining keywords among themselves
if unassigned:
    X = vectorizer.transform(unassigned)
    if X.shape[0] == 1:
        unclustered_out.append(unassigned[0])
    else:
        n_clusters = max(2, len(unassigned) // 3)
        n_clusters = min(n_clusters, len(unassigned))
        kmeans = KMeans(n_clusters=n_clusters, random_state=42)
        labels = kmeans.fit_predict(X)

        clusters = {}
        label_indices = {}
        for i, label in enumerate(labels):
            clusters.setdefault(label, []).append(unassigned[i])
            label_indices.setdefault(label, []).append(i)

        centers = kmeans.cluster_centers_
        for lbl in list(clusters.keys()):
            if len(clusters[lbl]) == 1 and len(clusters) > 1:
                idx = label_indices[lbl][0]
                vec = X[idx]
                sims = cosine_similarity(vec, centers)[0]
                sims[lbl] = -1
                best = sims.argmax()
                clusters.setdefault(best, []).append(clusters[lbl][0])
                label_indices.setdefault(best, []).append(idx)
                del clusters[lbl]
                del label_indices[lbl]

        ordered = sorted(clusters, key=lambda lbl: min(label_indices[lbl]))
        assigned.extend(clusters[lbl] for lbl in ordered)

# Try to merge or uncluster any single-keyword clusters
singles = [(i, c[0]) for i, c in enumerate(assigned) if len(c) == 1]
for idx, kw in singles:
    if not assigned[idx] or len(assigned[idx]) != 1:
        continue
    vec = vectorizer.transform([kw])
    best_idx = -1
    best_sim = 0.0
    for j, cluster in enumerate(assigned):
        if j == idx or not cluster:
            continue
        vecs = vectorizer.transform(cluster)
        centroid = vecs.mean(axis=0)
        sim = cosine_similarity(vec, centroid)[0][0]
        if sim > best_sim:
            best_sim = sim
            best_idx = j
    if best_idx >= 0 and best_sim >= 0.3:
        assigned[best_idx].append(kw)
    else:
        unclustered_out.append(kw)
    assigned[idx] = None

assigned = [c for c in assigned if c]

print(json.dumps({"clusters": assigned, "unclustered": unclustered_out}, ensure_ascii=False))

