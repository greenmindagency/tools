import sys
import os
import json
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans

# Step 1: Read keywords from stdin
instructions = os.environ.get("INSTRUCTIONS", "")
keywords = [line.strip() for line in sys.stdin if line.strip()]

if not keywords:
    print(json.dumps({"error": "No keywords provided."}))
    sys.exit(1)

# Step 2: Cluster with TF-IDF + KMeans
vectorizer = TfidfVectorizer()
X = vectorizer.fit_transform(keywords)

max_size = 5
for num in instructions.split():
    if num.isdigit():
        max_size = int(num)
        break
n_clusters = max(1, len(keywords) // max_size)
n_clusters = min(n_clusters if n_clusters else 1, len(keywords))
kmeans = KMeans(n_clusters=n_clusters, random_state=42)
labels = kmeans.fit_predict(X)

# Step 3: Output clusters
clusters = {}
for i, label in enumerate(labels):
    clusters.setdefault(label, []).append(keywords[i])

ordered = [kws for _, kws in sorted(clusters.items())]
print(json.dumps(ordered, ensure_ascii=False))
