import os
import sys
import numpy as np
import pandas as pd
import joblib
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import StandardScaler
from sklearn.pipeline import Pipeline
from sklearn.metrics import mean_squared_error, r2_score

FEATURE_NAMES = [
    "documents_count",
    "major_nc_count",
    "minor_nc_count",
    "conformity_count",
    "capas_count",
    "unsigned_docs_count",
    "draft_docs_count",
    "unsigned_ratio",
    "capa_closure_ratio",
    "evidence_completeness",
    "clause_4_risk",
    "clause_5_risk",
    "clause_6_risk",
    "clause_7_risk",
    "clause_8_risk",
    "clause_9_risk",
    "clause_10_risk"
]

def generate_training_data(n_samples=800):
    np.random.seed(42)
    rows = []
    
    for _ in range(n_samples):
        docs = np.random.randint(0, 35)
        major_nc = np.random.choice([0, 1, 2, 3], p=[0.6, 0.25, 0.1, 0.05])
        minor_nc = np.random.choice([0, 1, 2, 3, 4, 5], p=[0.35, 0.25, 0.2, 0.1, 0.05, 0.05])
        conformity = np.random.randint(0, 8)
        
        total_nc = major_nc + minor_nc
        if total_nc > 0:
            capas = np.random.randint(0, total_nc + 2)
        else:
            capas = np.random.randint(0, 3)
            
        unsigned = np.random.randint(0, max(1, docs // 2 + 1)) if docs > 0 else 0
        draft = np.random.randint(0, max(1, docs // 3 + 1)) if docs > 0 else 0
        
        unsigned_ratio = unsigned / docs if docs > 0 else 1.0
        capa_closure_ratio = min(1.0, capas / total_nc) if total_nc > 0 else 1.0
        
        evidence_completeness = min(1.0, (docs / 15.0) * (1.0 - (unsigned / max(1, docs))))
        
        c4_risk = np.random.choice([0.0, 0.3, 0.7, 1.0], p=[0.7, 0.15, 0.1, 0.05])
        c5_risk = np.random.choice([0.0, 0.3, 0.7, 1.0], p=[0.75, 0.15, 0.07, 0.03])
        c6_risk = np.random.choice([0.0, 0.3, 0.7, 1.0], p=[0.65, 0.2, 0.1, 0.05])
        c7_risk = min(1.0, (unsigned_ratio * 0.6) + ((1.0 - evidence_completeness) * 0.4) + (0.3 if minor_nc > 0 else 0.0))
        c8_risk = np.random.choice([0.0, 0.3, 0.7, 1.0], p=[0.6, 0.2, 0.15, 0.05])
        c9_risk = np.random.choice([0.0, 0.3, 0.7, 1.0], p=[0.7, 0.18, 0.08, 0.04])
        c10_risk = (1.0 - capa_closure_ratio) if total_nc > 0 else 0.0
        
        risk = 40.0
        risk += major_nc * 20.0
        risk += minor_nc * 6.5
        risk -= conformity * 3.5
        risk -= capa_closure_ratio * 16.0
        risk += (1.0 - evidence_completeness) * 22.0
        risk += unsigned_ratio * 12.0
        risk += (c7_risk * 8.0) + (c10_risk * 10.0) + (c8_risk * 5.0)
        
        if docs == 0:
            risk = max(risk, 75.0)
            
        noise = np.random.normal(0, 2.5)
        risk = np.clip(risk + noise, 5.0, 95.0)
        
        rows.append([
            docs,
            major_nc,
            minor_nc,
            conformity,
            capas,
            unsigned,
            draft,
            round(float(unsigned_ratio), 4),
            round(float(capa_closure_ratio), 4),
            round(float(evidence_completeness), 4),
            round(float(c4_risk), 4),
            round(float(c5_risk), 4),
            round(float(c6_risk), 4),
            round(float(c7_risk), 4),
            round(float(c8_risk), 4),
            round(float(c9_risk), 4),
            round(float(c10_risk), 4),
            round(float(risk), 2)
        ])
        
    cols = FEATURE_NAMES + ["compliance_risk_score"]
    df = pd.DataFrame(rows, columns=cols)
    return df

def train_and_save_model():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    df = generate_training_data(1000)
    
    csv_path = os.path.join(base_dir, "training_data.csv")
    df.to_csv(csv_path, index=False)
    
    X = df[FEATURE_NAMES]
    y = df["compliance_risk_score"]
    
    pipeline = Pipeline([
        ("scaler", StandardScaler()),
        ("regressor", RandomForestRegressor(n_estimators=150, max_depth=10, random_state=42))
    ])
    
    pipeline.fit(X, y)
    
    y_pred = pipeline.predict(X)
    r2 = r2_score(y, y_pred)
    mse = mean_squared_error(y, y_pred)
    
    model_path = os.path.join(base_dir, "risk_model.joblib")
    joblib.dump(pipeline, model_path)
    
    metadata = {
        "features": FEATURE_NAMES,
        "n_estimators": 150,
        "r2_score": round(float(r2), 4),
        "mse": round(float(mse), 4),
        "samples_trained": len(df)
    }
    meta_path = os.path.join(base_dir, "model_metadata.json")
    import json
    with open(meta_path, "w") as f:
        json.dump(metadata, f, indent=4)
        
    print("Multi-Clause Model trained and saved successfully.")
    print("R2 Score:", round(r2, 4))
    print("Dataset:", csv_path)
    print("Model File:", model_path)

if __name__ == "__main__":
    train_and_save_model()
