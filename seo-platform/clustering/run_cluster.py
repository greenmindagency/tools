import sys
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans

# Step 1: Read keywords from stdin
keywords = [line.strip() for line in sys.stdin if line.strip()]

if not keywords:
    print("❗ No keywords provided.")
    sys.exit(1)

# Step 2: Cluster with TF-IDF + KMeans
vectorizer = TfidfVectorizer()
X = vectorizer.fit_transform(keywords)

n_clusters = min(3, len(keywords))  # avoid errors on small input
kmeans = KMeans(n_clusters=n_clusters, random_state=42)
labels = kmeans.fit_predict(X)

# Step 3: Output clusters
clusters = {}
for i, label in enumerate(labels):
    clusters.setdefault(label, []).append(keywords[i])

for cid, kws in clusters.items():
    print(f"Cluster {cid + 1}:")
    for kw in kws:
        print(f"  - {kw}")
    print("")
