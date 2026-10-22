import os
import sys
import json
import re
import warnings
warnings.filterwarnings('ignore')
import numpy as np
import pandas as pd
import joblib

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

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, "risk_model.joblib")

CLAUSE_CATALOG = {
    "4.1": {
        "clause": "Clause 4.1 - Understanding the organization and its context",
        "title": "Context of the Organization",
        "desc": "Lack of structured internal and external risk monitoring factors and SWOT alignment affecting departmental strategic goals.",
        "ci": "Formalize departmental context reviews and risk matrices annually.",
        "actions": [
            "Conduct annual environmental scanning for organizational risks.",
            "Document departmental strategic context factors and issue registers.",
            "Align operational plans with identified organizational challenges."
        ]
    },
    "5.3": {
        "clause": "Clause 5.3 - Organizational roles, responsibilities and authorities",
        "title": "Organizational Roles & Authorities",
        "desc": "Ambiguity in delegated sign-off authorizations, unclear duty matrices, or lack of documented operational role handoffs.",
        "ci": "Maintain updated, signed organizational functional charts and authority matrices.",
        "actions": [
            "Publish clear delegation of authority and document approval matrix.",
            "Update job descriptions and responsibility profiles for all departmental units.",
            "Conduct periodic reviews of operational handoffs and sign-off accountability."
        ]
    },
    "6.1": {
        "clause": "Clause 6.1 - Actions to address risks and opportunities",
        "title": "Risk & Opportunity Planning",
        "desc": "Incomplete departmental risk and opportunity registers, or absence of proactive treatment plans for vulnerabilities.",
        "ci": "Systematize quarterly risk assessment tracking and preventive action plans.",
        "actions": [
            "Update departmental risk registers with quantitative severity ratings.",
            "Establish contingency plans for critical operational processes.",
            "Review risk mitigation progress during quarterly management meetings."
        ]
    },
    "7.1.2": {
        "clause": "Clause 7.1.2 - People and staffing resources",
        "title": "People & Staffing Resources",
        "desc": "Inadequate human resource allocation and lack of documented staffing capability evaluations for core QMS workflows.",
        "ci": "Strengthen workforce planning and workload distribution across departmental sections.",
        "actions": [
            "Evaluate staffing requirements against departmental operational volume.",
            "Document resource reallocation plans during peak operational periods.",
            "Conduct periodic workload assessments to maintain service quality."
        ]
    },
    "7.2": {
        "clause": "Clause 7.2 - Competence and training",
        "title": "Competence & Staff Training",
        "desc": "Gaps in documented training records, personnel competency evaluations, or lack of verified role onboarding profiles.",
        "ci": "Institute structured training matrices and post-training competency assessments.",
        "actions": [
            "Maintain updated skill matrices and competency evaluation files for all staff.",
            "Conduct targeted ISO 9001:2015 refresher training on core operational procedures.",
            "Institute documented post-training effectiveness assessments within 60 days of training."
        ]
    },
    "7.5.1": {
        "clause": "Clause 7.5.1 - Documented information general requirements",
        "title": "General Documented Information",
        "desc": "Absence of basic mandatory QMS documented information, operational procedures, or quality manual alignment.",
        "ci": "Establish fundamental baseline QMS operational procedures and record keeping.",
        "actions": [
            "Develop and formalize core departmental standard operating procedures.",
            "Create an evidence repository for mandatory quality records.",
            "Conduct orientation for personnel on required QMS documentation."
        ]
    },
    "7.5.2": {
        "clause": "Clause 7.5.2 - Creating and updating documented information",
        "title": "Creating & Updating Records",
        "desc": "Files with inadequate identification, missing titles/authors/approval status, or improper file formats lacking control.",
        "ci": "Enforce standardized document naming, titling, and formal authorization metadata.",
        "actions": [
            "Implement standardized document naming conventions and metadata headers across all files.",
            "Require author identification, version number, and approval signature on all records.",
            "Phase out uncontrolled plain text drafts in favor of standardized controlled templates."
        ]
    },
    "7.5.3": {
        "clause": "Clause 7.5.3 - Control of documented information",
        "title": "Control of Documented Information",
        "desc": "Deficiencies in document storage, version control, access retention, and unapproved or unsigned compliance files.",
        "ci": "Deploy structured digital archiving and pre-archival signature verification checkpoints.",
        "actions": [
            "Establish a centralized, version-controlled document repository organized by ISO clauses.",
            "Verify complete authorization and digital signatures before finalizing records.",
            "Conduct quarterly document control spot-checks to eliminate outdated or unapproved files."
        ]
    },
    "8.1": {
        "clause": "Clause 8.1 - Operational planning and control",
        "title": "Operational Planning & Control",
        "desc": "Incomplete operational standard operating procedures (SOPs) and missing objective evidence for process execution criteria.",
        "ci": "Standardize operational workflows, process mapping, and performance checklists.",
        "actions": [
            "Document end-to-end operational workflows with defined quality acceptance criteria.",
            "Implement operational process checklists to ensure consistent service execution.",
            "Conduct quarterly procedural adherence spot-checks across departmental units."
        ]
    },
    "8.4": {
        "clause": "Clause 8.4 - Control of externally provided processes, products and services",
        "title": "Procurement & External Providers",
        "desc": "Gaps in formal vendor evaluations, service level agreements, and procurement compliance documentation.",
        "ci": "Standardize supplier evaluation criteria and procurement compliance audit trails.",
        "actions": [
            "Maintain periodic vendor performance evaluation logs and supplier qualification matrices.",
            "Ensure complete documentation and signed approval forms for all procurement transactions.",
            "Implement pre-delivery inspection checklists to verify delivered goods against specifications."
        ]
    },
    "8.5.1": {
        "clause": "Clause 8.5.1 - Control of service provision",
        "title": "Control of Service Delivery",
        "desc": "Inconsistent frontline service delivery checkpoints and lack of documented evidence validating customer service outputs.",
        "ci": "Optimize frontline service delivery turnaround times and standard operating procedures.",
        "actions": [
            "Establish clear service delivery standards and citizen charter turnaround tracking.",
            "Implement service checklists to standardize customer/student service handling.",
            "Maintain complete operational logs for all delivered transactions."
        ]
    },
    "8.5.2": {
        "clause": "Clause 8.5.2 - Identification and traceability",
        "title": "Identification & Traceability",
        "desc": "Gaps in traceability records, retention of student/client credentials, or lack of unique transaction tracking identifiers.",
        "ci": "Enforce strict record traceability and unique tracking identifiers for all issued credentials.",
        "actions": [
            "Implement unique tracking numbers for all issued documents, certificates, and clearances.",
            "Maintain complete audit logs for credentials from initial application to final issuance.",
            "Conduct monthly reconciliation audits on issued credentials against student files."
        ]
    },
    "9.1.2": {
        "clause": "Clause 9.1.2 - Customer satisfaction",
        "title": "Customer/Student Satisfaction",
        "desc": "Incomplete or irregular client feedback collection, lack of feedback analysis, or delayed action on customer complaints.",
        "ci": "Implement systematic satisfaction feedback collection and quarterly analytics.",
        "actions": [
            "Deploy automated or regular client feedback surveys across all service points.",
            "Analyze quarterly customer satisfaction trends and identify service bottlenecks.",
            "Implement corrective action for any recurring feedback complaints."
        ]
    },
    "9.2": {
        "clause": "Clause 9.2 - Internal audit",
        "title": "Internal Quality Audit",
        "desc": "Gaps in periodic self-assessment verification, delayed audit checklist completion, or audit readiness deficiencies.",
        "ci": "Enhance internal audit readiness and systematic pre-audit self-evaluations.",
        "actions": [
            "Conduct scheduled pre-audit self-evaluations 30 days prior to formal audit cycles.",
            "Verify evidence repository completeness against ISO 9001:2015 clause requirements.",
            "Maintain organized compliance portfolios for all departmental processes."
        ]
    },
    "9.3": {
        "clause": "Clause 9.3 - Management review",
        "title": "Management Review",
        "desc": "Lack of recorded management review minutes, delayed follow-through on action items, or unmonitored quality objectives.",
        "ci": "Institutionalize structured quarterly management reviews and action tracking.",
        "actions": [
            "Schedule quarterly management review meetings with documented formal minutes.",
            "Track progress on quality objectives, audit results, and resource adequacy.",
            "Document executive management action items and monitor closure timelines."
        ]
    },
    "10.2": {
        "clause": "Clause 10.2 - Nonconformity and corrective action",
        "title": "Nonconformity & CAPA Management",
        "desc": "Delayed corrective action resolution cycles, ineffective root cause analysis, or repeat nonconformity recurrence.",
        "ci": "Strengthen root cause analysis protocols and ensure timely closure of all corrective actions.",
        "actions": [
            "Establish weekly CAPA review checkpoints to accelerate finding resolution.",
            "Implement structured 5-Why root cause analysis to eliminate recurring nonconformity sources.",
            "Enforce formal verification of effectiveness before closing out corrective actions."
        ]
    },
    "10.3": {
        "clause": "Clause 10.3 - Continual improvement",
        "title": "Continual Improvement",
        "desc": "Opportunities to systematize digital document verification workflows and enhance quality performance efficiency.",
        "ci": "Benchmark departmental best practices and implement automated quality analytics.",
        "actions": [
            "Benchmark successful departmental quality practices across other university units.",
            "Maintain continuous digital archival schedules and proactive record updates.",
            "Participate in cross-departmental peer quality audits to foster collaborative excellence."
        ]
    }
}

def get_trained_model():
    if not os.path.exists(MODEL_PATH):
        from train_model import train_and_save_model
        train_and_save_model()
    return joblib.load(MODEL_PATH)

def extract_clause_historical_signals(office_data):
    findings = office_data.get('historical_findings', [])
    filenames = office_data.get('filenames', [])
    doc_clauses = office_data.get('doc_clauses', [])
    capas = office_data.get('capas', [])
    capas_count = int(office_data.get('capas_count', 0))
    major_nc = int(office_data.get('major_nc_count', 0))
    minor_nc = int(office_data.get('minor_nc_count', 0))
    total_nc = major_nc + minor_nc
    
    clause_scores = {
        "4.1": 0.0, "5.3": 0.0, "6.1": 0.0, "7.1.2": 0.0, "7.2": 0.0,
        "7.5.1": 0.0, "7.5.2": 0.0, "7.5.3": 0.0, "8.1": 0.0, "8.4": 0.0,
        "8.5.1": 0.0, "8.5.2": 0.0, "9.1.2": 0.0, "9.2": 0.0, "9.3": 0.0,
        "10.2": 0.0, "10.3": 0.0
    }
    
    clause_findings_map = {k: [] for k in clause_scores}
    
    for f in findings:
        f_text = f.get('findings', '')
        f_type = f.get('finding_type', 'Minor Nonconformity')
        weight = 3.5 if f_type == 'Major Nonconformity' else (2.0 if f_type == 'Minor Nonconformity' else -0.5)
        
        matched_clauses = set()
        for c_match in re.finditer(r'clause\s*([0-9]+(?:\.[0-9]+)*)', f_text, re.IGNORECASE):
            c_str = c_match.group(1).strip()
            for k in clause_scores:
                if c_str == k or (c_str.startswith(k) and len(c_str) > len(k)) or (k.startswith(c_str) and len(c_str) >= 3):
                    matched_clauses.add(k)
                    
        f_lower = f_text.lower()
        if any(w in f_lower for w in ['competence', 'training', 'skill', 'qualification', 'onboarding']):
            matched_clauses.add("7.2")
        if any(w in f_lower for w in ['people', 'staffing', 'personnel development']):
            matched_clauses.add("7.1.2")
        if any(w in f_lower for w in ['naming', 'title', 'format', 'creating and updating', 'camscanner', 'txt', 'docx', 'file1', 'file2', 'generic']):
            matched_clauses.add("7.5.2")
        if any(w in f_lower for w in ['control of documented', 'unsigned', 'unapproved', 'version', 'retention', 'archive', 'metadata']):
            matched_clauses.add("7.5.3")
        if any(w in f_lower for w in ['absence of documented', 'no evidence', 'lack of documented information']):
            matched_clauses.add("7.5.1")
        if any(w in f_lower for w in ['traceability', 'transcript', 'enrollment', 'student records', 'credential']):
            matched_clauses.add("8.5.2")
        if any(w in f_lower for w in ['swot', 'risk assessment', 'risk register', 'risk planning']):
            matched_clauses.add("6.1")
        if any(w in f_lower for w in ['procurement', 'supplier', 'vendor', 'externally provided', 'bac']):
            matched_clauses.add("8.4")
        if any(w in f_lower for w in ['corrective action', 'root cause', 'recurrence', 'unresolved']):
            matched_clauses.add("10.2")
            
        for mc in matched_clauses:
            clause_scores[mc] += weight
            clause_findings_map[mc].append(f_text)
            
    unsigned = sum(1 for fn in filenames if any(k in fn.lower() for k in ['unsigned', 'pending_sig', 'no_sig', 'unapproved']))
    draft = sum(1 for fn in filenames if any(k in fn.lower() for k in ['draft', 'temp', 'wip', 'v0.', '.txt']))
    generic_names = sum(1 for fn in filenames if any(k in fn.lower() for k in ['file', 'camscanner', 'doc1', 'scan']))
    
    if unsigned > 0:
        clause_scores["7.5.3"] += unsigned * 1.5
    if generic_names > 0 or draft > 0:
        clause_scores["7.5.2"] += (generic_names + draft) * 1.2
    if len(filenames) == 0:
        clause_scores["7.5.1"] += 4.0
        clause_scores["8.1"] += 2.0
    elif len(filenames) == 1 and any('group' in fn.lower() or 'project' in fn.lower() for fn in filenames):
        clause_scores["7.5.1"] += 5.0
        
    if total_nc > 0 and capas_count < total_nc:
        clause_scores["10.2"] += (total_nc - capas_count) * 1.8
        
    for c_id in doc_clauses:
        if c_id == 4:
            clause_scores["4.1"] = max(0.0, clause_scores["4.1"] - 0.5)
            clause_scores["6.1"] = max(0.0, clause_scores["6.1"] - 0.5)
        elif c_id == 7:
            clause_scores["7.5.3"] = max(0.0, clause_scores["7.5.3"] - 0.5)
        elif c_id == 8:
            clause_scores["8.1"] = max(0.0, clause_scores["8.1"] - 0.5)
            
    return clause_scores, clause_findings_map

def extract_features(office_data):
    docs = int(office_data.get('documents_count', 0))
    major_nc = int(office_data.get('major_nc_count', 0))
    minor_nc = int(office_data.get('minor_nc_count', 0))
    conformity = int(office_data.get('conformity_count', 0))
    capas = int(office_data.get('capas_count', 0))
    
    filenames = office_data.get('filenames', [])
    unsigned = sum(1 for f in filenames if any(k in f.lower() for k in ['unsigned', 'pending_sig', 'no_sig', 'unapproved']))
    draft = sum(1 for f in filenames if any(k in f.lower() for k in ['draft', 'temp', 'wip', 'v0.']))
    
    total_nc = major_nc + minor_nc
    unsigned_ratio = unsigned / docs if docs > 0 else 1.0
    capa_closure_ratio = min(1.0, capas / total_nc) if total_nc > 0 else 1.0
    evidence_completeness = min(1.0, (docs / 15.0) * (1.0 - (unsigned / max(1, docs))))
    
    clause_scores, _ = extract_clause_historical_signals(office_data)
    
    c4_risk = min(1.0, (clause_scores.get("4.1", 0.0) + clause_scores.get("4.2", 0.0)) / 5.0)
    c5_risk = min(1.0, (clause_scores.get("5.3", 0.0)) / 5.0)
    c6_risk = min(1.0, (clause_scores.get("6.1", 0.0)) / 5.0)
    c7_risk = min(1.0, (clause_scores.get("7.2", 0.0) + clause_scores.get("7.5.2", 0.0) + clause_scores.get("7.5.3", 0.0) + clause_scores.get("7.1.2", 0.0)) / 8.0)
    c8_risk = min(1.0, (clause_scores.get("8.1", 0.0) + clause_scores.get("8.4", 0.0) + clause_scores.get("8.5.1", 0.0) + clause_scores.get("8.5.2", 0.0)) / 8.0)
    c9_risk = min(1.0, (clause_scores.get("9.1.2", 0.0) + clause_scores.get("9.2", 0.0) + clause_scores.get("9.3", 0.0)) / 6.0)
    c10_risk = min(1.0, (clause_scores.get("10.2", 0.0) + clause_scores.get("10.3", 0.0)) / 5.0)
    
    return [
        docs,
        major_nc,
        minor_nc,
        conformity,
        capas,
        unsigned,
        draft,
        unsigned_ratio,
        capa_closure_ratio,
        evidence_completeness,
        c4_risk,
        c5_risk,
        c6_risk,
        c7_risk,
        c8_risk,
        c9_risk,
        c10_risk
    ]

def get_risk_drivers(features, model_pipeline):
    regressor = model_pipeline.named_steps.get('regressor')
    importances = regressor.feature_importances_ if hasattr(regressor, 'feature_importances_') else np.ones(len(FEATURE_NAMES))
    
    docs, major_nc, minor_nc, conformity, capas, unsigned, draft, unsigned_ratio, capa_closure_ratio, evidence_comp = features[:10]
    
    driver_candidates = []
    if docs == 0:
        driver_candidates.append((importances[0] * 2.5, "Total absence of uploaded verification documents"))
    elif docs < 4:
        driver_candidates.append((importances[0] * 1.5, f"Low volume of objective evidence ({docs} files uploaded)"))
        
    if major_nc > 0:
        driver_candidates.append((importances[1] * (1.0 + major_nc), f"Unresolved Major Nonconformities ({major_nc} logged)"))
        
    if minor_nc > 1:
        driver_candidates.append((importances[2] * (1.0 + minor_nc * 0.5), f"Recurring Minor Nonconformities ({minor_nc} logged)"))
        
    if unsigned > 0:
        driver_candidates.append((importances[5] * (1.0 + unsigned_ratio), f"Unsigned or unverified documents detected ({unsigned} files)"))
        
    if draft > 0:
        driver_candidates.append((importances[6], f"Incomplete draft documents present ({draft} files)"))
        
    if total_nc := (major_nc + minor_nc):
        if capas < total_nc:
            driver_candidates.append((importances[8] * (1.0 - capa_closure_ratio + 0.5), f"Incomplete Corrective Action Plans ({capas}/{total_nc} resolved)"))
            
    if evidence_comp < 0.35 and docs > 0:
        driver_candidates.append((importances[9] * 1.2, "Low document evidence completeness index"))
        
    if not driver_candidates:
        if conformity > 2:
            driver_candidates.append((1.0, "Strong baseline of validated quality conformity records"))
        else:
            driver_candidates.append((1.0, "Consistent record retention and verified document signatures"))
            
    driver_candidates.sort(key=lambda x: x[0], reverse=True)
    return [d[1] for d in driver_candidates[:3]]

def predict_with_scikit_learn(offices_payload):
    model_pipeline = get_trained_model()
    
    predictions = []
    for office in offices_payload:
        feat_vector = extract_features(office)
        df_feat = pd.DataFrame([feat_vector], columns=FEATURE_NAMES)
        
        raw_score = float(model_pipeline.predict(df_feat)[0])
        bounded_score = int(np.clip(round(raw_score), 5, 95))
        
        if bounded_score >= 65:
            cat = "High"
        elif bounded_score >= 35:
            cat = "Medium"
        else:
            cat = "Low"
            
        drivers = get_risk_drivers(feat_vector, model_pipeline)
        clause_scores, clause_findings_map = extract_clause_historical_signals(office)
        
        predictions.append({
            "office_id": office.get("office_id"),
            "office_name": office.get("office_name"),
            "office_abbr": office.get("office_abbr"),
            "compliance_risk_score": bounded_score,
            "risk_category": cat,
            "ml_model": "Scikit-Learn Random Forest",
            "risk_drivers": drivers,
            "clause_scores": clause_scores,
            "clause_findings_map": clause_findings_map,
            "features_analyzed": feat_vector
        })
        
    return predictions

def build_multi_clause_fallback(office_data, pred):
    clause_scores, clause_findings_map = extract_clause_historical_signals(office_data)
    score = pred["compliance_risk_score"]
    
    ranked_clauses = sorted(
        [(k, v) for k, v in clause_scores.items() if v > 0.0],
        key=lambda x: x[1],
        reverse=True
    )
    
    forecasted_clauses = []
    
    if ranked_clauses:
        for c_key, c_val in ranked_clauses[:3]:
            cat_info = CLAUSE_CATALOG.get(c_key, CLAUSE_CATALOG["7.5.3"])
            r_level = "High" if c_val >= 3.0 or score >= 65 else ("Medium" if c_val >= 1.5 or score >= 40 else "Low")
            
            snippets = clause_findings_map.get(c_key, [])
            custom_desc = cat_info["desc"]
            if snippets:
                first_snip = snippets[0]
                if len(first_snip) > 180:
                    first_snip = first_snip[:180] + "..."
                custom_desc = f"{first_snip} Evidence indicates elevated risk under {cat_info['clause']}."
                
            forecasted_clauses.append({
                "clause": cat_info["clause"],
                "risk_level": r_level,
                "description": custom_desc,
                "area_for_improvement": cat_info["ci"],
                "proactive_preventive_actions": cat_info["actions"]
            })
    else:
        if score >= 65:
            cat_info = CLAUSE_CATALOG["7.5.3"]
            r_level = "High"
        elif score >= 35:
            cat_info = CLAUSE_CATALOG["8.1"]
            r_level = "Medium"
        else:
            cat_info = CLAUSE_CATALOG["10.3"]
            r_level = "Low"
            
        forecasted_clauses.append({
            "clause": cat_info["clause"],
            "risk_level": r_level,
            "description": cat_info["desc"],
            "area_for_improvement": cat_info["ci"],
            "proactive_preventive_actions": cat_info["actions"]
        })
        
    primary = forecasted_clauses[0]
    return {
        "office_id": office_data.get("office_id"),
        "office_name": office_data.get("office_name"),
        "office_abbr": office_data.get("office_abbr"),
        "compliance_risk_score": score,
        "risk_category": pred["risk_category"],
        "ml_model": "Scikit-Learn Random Forest",
        "risk_drivers": pred["risk_drivers"],
        "auditor_findings": office_data.get("historical_findings", []),
        "assigned_auditors": office_data.get("assigned_auditors", []),
        "forecasted_clauses": forecasted_clauses,
        "forecasted_nonconformity": {
            "clause": primary["clause"],
            "description": primary["description"],
            "risk_level": primary.get("risk_level", pred["risk_category"])
        },
        "area_for_improvement": primary["area_for_improvement"],
        "proactive_preventive_actions": primary["proactive_preventive_actions"]
    }

def generate_hybrid_forecast(offices_payload, ml_predictions):
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        env_path = os.path.join(os.path.dirname(BASE_DIR), ".env")
        if os.path.exists(env_path):
            with open(env_path, "r") as f:
                for line in f:
                    if line.startswith("GEMINI_API_KEY="):
                        api_key = line.split("=", 1)[1].strip()
                        break
                        
    context_data = []
    for i, office in enumerate(offices_payload):
        pred = ml_predictions[i]
        c_scores, c_map = extract_clause_historical_signals(office)
        context_data.append({
            "office_id": office.get("office_id"),
            "office_name": office.get("office_name"),
            "office_abbr": office.get("office_abbr"),
            "compliance_risk_score": pred["compliance_risk_score"],
            "risk_category": pred["risk_category"],
            "risk_drivers": pred["risk_drivers"],
            "documents_count": office.get("documents_count", 0),
            "major_nc_count": office.get("major_nc_count", 0),
            "minor_nc_count": office.get("minor_nc_count", 0),
            "conformity_count": office.get("conformity_count", 0),
            "capas_count": office.get("capas_count", 0),
            "filenames": office.get("filenames", []),
            "historical_findings": [f.get('findings', '') if isinstance(f, dict) else str(f) for f in office.get('historical_findings', [])],
            "detected_historical_clause_signals": {k: v for k, v in c_scores.items() if v > 0}
        })

    if not api_key:
        return [build_multi_clause_fallback(offices_payload[i], ml_predictions[i]) for i in range(len(offices_payload))]

    try:
        import google.generativeai as genai
        genai.configure(api_key=api_key)
        
        prompt = f"""You are a Lead ISO 9001:2015 Quality Management Auditor and Predictive Risk Analyst.
A Scikit-Learn Machine Learning pipeline has analyzed the compliance data, document evidence, and historical audit findings across several offices.

CRITICAL INSTRUCTIONS:
1. You must identify and forecast ALL ISO 9001:2015 Clauses (Clauses 4 through 10) that exhibit vulnerabilities or risk based on each office's actual historical audit findings, document gaps, and compliance patterns.
2. DO NOT restrict an office to only 1 clause if multiple clauses have risk signals in the historical findings or documents.
3. For each identified risk clause under an office, output its specific Clause title, risk level (High, Medium, Low), detailed audit gap description citing the historical findings/files, continuous improvement goal, and 3 proactive preventive mitigations.
4. Output an array 'forecasted_clauses' containing 1 to 3 relevant clauses showing risk for that office.

Office Compliance & Historical Audit Data:
{json.dumps(context_data, indent=2)}

Strictly output a valid JSON list of objects matching this exact schema for each office. DO NOT wrap with markdown backticks:
[
  {{
    "office_id": <int>,
    "office_name": "<string>",
    "office_abbr": "<string>",
    "compliance_risk_score": <int, preserve ML score>,
    "risk_category": "<string, Low|Medium|High, preserve ML category>",
    "ml_model": "Scikit-Learn Random Forest",
    "risk_drivers": [<strings from ML analysis>],
    "forecasted_clauses": [
      {{
        "clause": "Clause <number>.<number> - <Clause Title>",
        "risk_level": "High|Medium|Low",
        "description": "<Detailed audit gap explanation citing specific historical findings or file gaps>",
        "area_for_improvement": "<Continual improvement target for this clause>",
        "proactive_preventive_actions": [
          "<Action 1>",
          "<Action 2>",
          "<Action 3>"
        ]
      }}
    ],
    "forecasted_nonconformity": {{
      "clause": "<Top primary clause from forecasted_clauses>",
      "description": "<Primary description>"
    }},
    "area_for_improvement": "<Primary continual improvement target>",
    "proactive_preventive_actions": [
      "<Primary Action 1>",
      "<Primary Action 2>",
      "<Primary Action 3>"
    ]
  }}
]
"""
        model = genai.GenerativeModel("gemini-2.5-flash")
        response = model.generate_content(prompt)
        text = response.text.strip()
        if text.startswith("```json"):
            text = text[7:]
        if text.startswith("```"):
            text = text[3:]
        if text.endswith("```"):
            text = text[:-3]
        text = text.strip()
        parsed = json.loads(text)
        
        for i, p in enumerate(parsed):
            if "forecasted_clauses" not in p or not p["forecasted_clauses"]:
                if "forecasted_nonconformity" in p and p["forecasted_nonconformity"]:
                    p["forecasted_clauses"] = [{
                        "clause": p["forecasted_nonconformity"].get("clause", "Clause 7.5.3 - Control of documented information"),
                        "risk_level": p.get("risk_category", "High"),
                        "description": p["forecasted_nonconformity"].get("description", ""),
                        "area_for_improvement": p.get("area_for_improvement", ""),
                        "proactive_preventive_actions": p.get("proactive_preventive_actions", [])
                    }]
            if i < len(offices_payload):
                p["auditor_findings"] = offices_payload[i].get("historical_findings", [])
                p["assigned_auditors"] = offices_payload[i].get("assigned_auditors", [])
                    
        return parsed
    except Exception:
        return [build_multi_clause_fallback(offices_payload[i], ml_predictions[i]) for i in range(len(offices_payload))]

def main():
    try:
        raw_input = sys.stdin.read().strip()
        if not raw_input:
            print(json.dumps([]))
            return
            
        offices_payload = json.loads(raw_input)
        if not isinstance(offices_payload, list) or len(offices_payload) == 0:
            print(json.dumps([]))
            return
            
        ml_preds = predict_with_scikit_learn(offices_payload)
        final_results = generate_hybrid_forecast(offices_payload, ml_preds)
        print(json.dumps(final_results))
    except Exception as e:
        print(json.dumps([{"error": str(e)}]))

if __name__ == "__main__":
    main()

