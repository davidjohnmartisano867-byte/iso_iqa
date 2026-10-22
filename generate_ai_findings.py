import os
import sys
import json
import random

def generate_fallback_findings(data):
    office_name = data.get("office_name", "Office")
    office_abbr = data.get("office_abbr", "OFFICE")
    documents = data.get("documents", [])
    
    findings = []
    
    if not documents:
        findings.append({
            "finding_type": "Major Nonconformity",
            "findings": f"There are no uploaded documented evidences or records for {office_name} ({office_abbr}) under any ISO 9001 clause. Clause 7.5.3 requires documented information of the quality management system to be controlled and available where needed.",
            "deadline_days": 30
        })
    else:
        unsigned_docs = []
        draft_docs = []
        for d in documents:
            filename = d.get("filename", "") if isinstance(d, dict) else str(d)
            fn_lower = filename.lower()
            if "unsigned" in fn_lower or "no_sig" in fn_lower:
                unsigned_docs.append(filename)
            elif "draft" in fn_lower or "temp" in fn_lower:
                draft_docs.append(filename)
                
        if unsigned_docs:
            findings.append({
                "finding_type": "Minor Nonconformity",
                "findings": f"Uploaded compliance records under {office_abbr} lack required digital authorization signatures: {', '.join(unsigned_docs[:2])}. Clause 7.5.2 mandates review and approval controls before formal release.",
                "deadline_days": 45
            })
            
        if draft_docs:
            findings.append({
                "finding_type": "Minor Nonconformity",
                "findings": f"Document files for {office_abbr} remain in draft or temporary format without finalization: {', '.join(draft_docs[:2])}. Clause 7.5.3 requires controlled documented information.",
                "deadline_days": 45
            })
            
    if not findings:
        findings.append({
            "finding_type": "Conformity",
            "findings": f"The documented information of {office_name} ({office_abbr}) is aligned with the ISO 9001:2015 standard. Operational records are controlled and properly verified.",
            "deadline_days": 90
        })
        
    return findings

def main():
    if len(sys.argv) < 2:
        print(json.dumps([]))
        sys.exit(1)
        
    try:
        data = json.loads(sys.argv[1])
    except Exception as e:
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
        fallback = generate_fallback_findings(data)
        print(json.dumps(fallback))
        sys.exit(0)
        
    try:
        import google.generativeai as genai
        genai.configure(api_key=api_key)
        
        prompt = """You are a senior ISO 9001:2015 Lead Auditor. Your task is to analyze the following compliance data for the office: {office_name} ({office_abbr}) and identify realistic, highly professional audit findings, gaps, and nonconformities.

Compliance Data:
- Office Name: {office_name}
- Office Abbreviation: {office_abbr}
- Uploaded Evidences / Documents: {documents}
- Existing Registered Gaps: {existing_findings}

Based on these inputs, perform a gap analysis. Identify 1 to 3 realistic, tailored findings.
Rules:
1. If there are no or very few supporting documents, identify it as a Major Nonconformity under Documented Information (Clause 7.5.3).
2. If documents are unapproved, unsigned, or drafts, identify a Minor Nonconformity under Creating and Updating (Clause 7.5.2).
3. If there are existing findings, ensure your generated findings complement them without duplicating them.
4. For each finding, output:
   - finding_type: must be strictly one of: 'Major Nonconformity', 'Minor Nonconformity', 'Conformity'.
   - findings: detailed description of the gap details referencing relevant ISO clauses.
   - deadline_days: an integer representing days to resolve (e.g. 30 for Major Nonconformity, 45 or 60 for Minor Nonconformity, 90 for Conformity).

Respond strictly with a valid JSON array of objects only. Each object must have keys: 'finding_type', 'findings', 'deadline_days'. Do not include markdown formatting or backticks.
"""
        prompt = prompt.format(
            office_name=data.get("office_name", "Office"),
            office_abbr=data.get("office_abbr", "OFFICE"),
            documents=json.dumps(data.get("documents", [])),
            existing_findings=json.dumps(data.get("existing_findings", []))
        )
        
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
    except Exception as e:
        fallback = generate_fallback_findings(data)
        print(json.dumps(fallback))

if __name__ == "__main__":
    main()
