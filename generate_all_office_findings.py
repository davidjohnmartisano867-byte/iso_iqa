import os
import sys
import json

def generate_fallback_findings(offices):
    results = []
    for office in offices:
        office_id = office.get("office_id")
        office_name = office.get("office_name", "Office")
        office_abbr = office.get("office_abbr", "OFFICE")
        documents = office.get("documents", [])
        
        if not documents:
            results.append({
                "office_id": office_id,
                "finding_type": "Major Nonconformity",
                "findings": f"During the QMS audit of {office_name} ({office_abbr}), a total absence of supporting documented evidence or records was observed. ISO 9001:2015 Clause 7.5.3 requires QMS documented information to be controlled and available to ensure suitability and protection.",
                "deadline_days": 7
            })
            continue

        unsigned_docs = []
        draft_docs = []
        for doc in documents:
            doc_lower = doc.lower()
            if "unsigned" in doc_lower or "no_sig" in doc_lower:
                unsigned_docs.append(doc)
            elif "draft" in doc_lower or "temp" in doc_lower:
                draft_docs.append(doc)

        if unsigned_docs:
            results.append({
                "office_id": office_id,
                "finding_type": "Minor Nonconformity",
                "findings": f"The audit of {office_abbr} revealed multiple critical records without required administrative signatures or formal authorizations: {', '.join(unsigned_docs[:2])}. Under ISO 9001:2015 Clause 7.5.2, documented information must include appropriate review and approval for suitability and adequacy prior to release.",
                "deadline_days": 7
            })
            continue

        if draft_docs:
            results.append({
                "office_id": office_id,
                "finding_type": "Minor Nonconformity",
                "findings": f"Documented files under {office_abbr} are maintained in an unapproved 'DRAFT' or temporary state: {', '.join(draft_docs[:2])}. ISO 9001:2015 Clause 7.5.3.1 requires documented information to be controlled to ensure it is adequately protected from unintended alterations.",
                "deadline_days": 7
            })
            continue

        results.append({
            "office_id": office_id,
            "finding_type": "Conformity",
            "findings": f"The QMS audit of {office_name} ({office_abbr}) shows satisfactory compliance. Documented information is properly maintained, controlled, and supported by signed evidence. Verification of file control satisfies Clause 7.5 requirements.",
            "deadline_days": 7
        })

    return results

def main():
    if len(sys.argv) < 2:
        print(json.dumps([]))
        sys.exit(1)
        
    try:
        offices = json.loads(sys.argv[1])
    except Exception:
        print(json.dumps([]))
        sys.exit(1)
        
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")
        if os.path.exists(env_path):
            with open(env_path, "r") as f:
                for line in f:
                    if line.startswith("GEMINI_API_KEY="):
                        api_key = line.split("=", 1)[1].strip()
                        break
                        
    if not api_key:
        fallback = generate_fallback_findings(offices)
        print(json.dumps(fallback))
        sys.exit(0)
        
    try:
        import google.generativeai as genai
        genai.configure(api_key=api_key)
        
        prompt = """You are a senior ISO 9001:2015 Quality Management System Lead Auditor.
You are tasked with conducting a comprehensive, professional compliance gap analysis for the following academic and administrative university offices.

Office Compliance Data:
{offices_json}

Ensure your audit evaluates gaps like:
1. Absence of needed evidentiary documents and quality records (Clause 7.5.3).
2. Lack of signatures, approval controls, or filenames indicating 'draft' or 'temp' status (Clause 7.5.2).
3. Document control and record retention weaknesses.

Rules for Output:
- Generate 1 to 2 professional findings for each office.
- For each finding, output:
  - office_id: the exact integer ID of the office from the input.
  - finding_type: must be strictly one of: 'Major Nonconformity', 'Minor Nonconformity', 'Conformity'.
  - findings: a detailed, highly professional audit finding referencing specific ISO 9001:2015 clauses and specific gaps (like lack of needed documents, lack of signatures on specific files).
  - deadline_days: strictly set to 7.

Return ONLY a valid JSON array of objects. Do not wrap in markdown code blocks or use backticks. Follow this exact format:
[
  {{"office_id": 1, "finding_type": "Minor Nonconformity", "findings": "description here", "deadline_days": 7}}
]
"""
        prompt = prompt.format(offices_json=json.dumps(offices, indent=2))
        
        response = None
        last_err = None
        for model_name in ["gemini-1.5-flash", "gemini-2.5-flash", "gemini-pro"]:
            try:
                model = genai.GenerativeModel(model_name)
                response = model.generate_content(prompt)
                break
            except Exception as e:
                last_err = e
                
        if not response:
            raise last_err
            
        response_text = response.text.strip()
        if "```json" in response_text:
            response_text = response_text.split("```json")[1].split("```")[0].strip()
        elif "```" in response_text:
            response_text = response_text.split("```")[1].split("```")[0].strip()
            
        res_data = json.loads(response_text)
        print(json.dumps(res_data))
    except Exception:
        fallback = generate_fallback_findings(offices)
        print(json.dumps(fallback))

if __name__ == "__main__":
    main()
