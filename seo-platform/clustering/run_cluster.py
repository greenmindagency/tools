import sys
import json
import os
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans
from sklearn.metrics.pairwise import cosine_similarity

# Step 1: Read keywords from stdin
keywords = [line.strip() for line in sys.stdin if line.strip()]
instructions = os.environ.get("INSTRUCTIONS", "")

if not keywords:
    print(json.dumps({"error": "No keywords provided."}))
    sys.exit(1)

# Step 2: Cluster with TF-IDF + KMeans
vectorizer = TfidfVectorizer()
X = vectorizer.fit_transform(keywords)

n_clusters = max(2, len(keywords) // 3) if len(keywords) > 1 else 1
n_clusters = min(n_clusters, len(keywords))
kmeans = KMeans(n_clusters=n_clusters, random_state=42)
labels = kmeans.fit_predict(X)

clusters = {}
label_indices = {}
for i, label in enumerate(labels):
    clusters.setdefault(label, []).append(keywords[i])
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

ordered_labels = sorted(clusters, key=lambda lbl: min(label_indices[lbl]))
ordered = [clusters[lbl] for lbl in ordered_labels]
print(json.dumps(ordered, ensure_ascii=False))
