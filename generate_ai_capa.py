import os
import sys
import json

def generate_fallback_capa(finding):
    findings = finding.get("findings", "").lower()
    root_cause_input = finding.get("root_cause", "").lower()
    
    target_text = root_cause_input if root_cause_input else findings
    
    if "training" in target_text or "competenc" in target_text or "aware" in target_text or "onboard" in target_text or "staff" in target_text:
        root_causes = [
            "Inadequate staff onboarding and lack of structured role-specific procedure training across operational workflows.",
            "Absence of formal training evaluation and competency assessment records for personnel performing quality-critical tasks.",
            "Insufficient communication of QMS objectives and ISO 9001:2015 standard awareness among department members."
        ]
        actions = [
            "Conduct immediate competency-based training for all responsible staff on the correct procedures and document signed training evaluation records.",
            "Establish a departmental Training & Competency Matrix with scheduled annual refresher workshops and new personnel onboarding checklists.",
            "Implement a supervised verification period before newly trained staff execute quality-critical tasks independently."
        ]
    elif "signature" in target_text or "unsigned" in target_text or "approv" in target_text or "authoriz" in target_text:
        root_causes = [
            "Incomplete document sign-off verification protocol prior to archiving compliance records.",
            "Absence of an automated approval routing workflow and pending signature reminder system.",
            "Lack of clearly designated approval authorities in the departmental document control matrix."
        ]
        actions = [
            "Audit all recent records to secure missing approvals, establish a digital signature routing matrix, and verify complete authorization on 100% of archived documents.",
            "Implement an automated notification and sign-off checklist to prevent documents from being finalized without verified designated reviewer approvals.",
            "Update the Document Control Matrix with explicit primary and backup sign-off authorities, and conduct orientation for all departmental approvers."
        ]
    elif "document" in target_text or "evidence" in target_text or "record" in target_text or "storage" in target_text:
        root_causes = [
            "Decentralized document storage practices leading to gaps in traceability and record retention management.",
            "Absence of standardized document archiving and cataloging protocols structured by ISO clauses.",
            "Lack of designated trained document custodians responsible for record lifecycle and archiving controls."
        ]
        actions = [
            "Conduct an immediate evidence inventory to locate and catalog missing records into a secure, centralized ISO-structured digital repository.",
            "Establish standard naming, versioning, and metadata tagging guidelines for all departmental documents with mandatory custodian sign-off.",
            "Designate and train certified Document Custodians for each section responsible for quarterly record lifecycle and retention reviews."
        ]
    elif "calibrat" in target_text or "equip" in target_text or "tool" in target_text:
        root_causes = [
            "Inconsistent adherence to preventive maintenance and calibration schedules for critical monitoring equipment.",
            "Absence of an automated calibration tracking log and equipment status tagging in operational areas.",
            "Lack of verified traceability certificates for inspection and testing tools used in quality verification."
        ]
        actions = [
            "Immediately tag and recalibrate all affected equipment against traceable national standards before further operational use.",
            "Deploy a centralized preventive maintenance and calibration schedule with automated alerts 30 days prior to expiration.",
            "Conduct quarterly spot-audits on all active measurement devices to ensure valid calibration stickers and logs are maintained."
        ]
    else:
        root_causes = [
            "Insufficient operational compliance monitoring and lack of standardized periodic review cycles.",
            "Absence of formalized standard operating procedure documentation and lack of structured process-mapping guidelines.",
            "Inadequate verification spot-checks and absence of centralized tracking for nonconformity resolutions."
        ]
        actions = [
            "Perform a comprehensive review of affected operational areas, update relevant standard operating procedures, and conduct re-training for personnel.",
            "Establish a monthly operational compliance audit schedule with designated checklists to identify and remediate deviations early.",
            "Implement a centralized Corrective and Preventive Action (CAPA) tracking dashboard to monitor milestone completion and effectiveness reviews."
        ]
        
    return {
        "root_causes": root_causes,
        "root_cause": root_causes[0],
        "corrective_actions": actions,
        "corrective_action": actions[0]
    }

def main():
    try:
        input_data = sys.stdin.read()
        finding = json.loads(input_data)
    except Exception:
        print(json.dumps({"root_causes": [], "root_cause": "", "corrective_actions": [], "corrective_action": ""}))
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
        fallback = generate_fallback_capa(finding)
        print(json.dumps(fallback))
        sys.exit(0)
        
    try:
        import google.generativeai as genai
        genai.configure(api_key=api_key)
        
        prompt = """You are a senior ISO 9001:2015 QMS Consultant. Your task is to recommend 3 to 4 actionable, professional corrective and preventive action (CAPA) options tailored to the following audit finding and the identified root cause:

Finding Type: {finding_type}
Finding Details: {findings}
Selected/Entered Root Cause: {root_cause}

Provide:
1. 'root_causes': An array of 3 to 4 distinct, concise root causes explaining why the breakdown occurred.
2. 'corrective_actions': An array of 3 to 4 distinct, comprehensive, and actionable corrective/preventive action solutions DIRECTLY ADDRESSING AND ELIMINATING the specified root cause ("{root_cause}") as well as the finding details.
3. 'corrective_action': The primary recommended corrective action plan from the options above.

CRITICAL RULE: Corrective action options must be practical, specific solutions designed to eliminate the root cause and prevent recurrence. Suggest ONLY concrete root causes and CAPA plan steps. Do NOT reiterate or summarize the findings text.

Respond strictly with a valid JSON object only with keys "root_causes" (array of strings), "corrective_actions" (array of strings), and "corrective_action" (string). Do not include markdown code blocks or backticks. Just output raw JSON.
"""
        prompt = prompt.format(
            finding_type=finding.get("finding_type", "Minor Nonconformity"),
            findings=finding.get("findings", ""),
            root_cause=finding.get("root_cause", "None specified")
        )
        
        response = None
        last_err = None
        for model_name in ["gemini-2.5-flash", "gemini-1.5-flash", "gemini-pro"]:
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
        fallback = generate_fallback_capa(finding)
        
        root_causes = res_data.get("root_causes", [])
        if not isinstance(root_causes, list) or len(root_causes) == 0:
            if "root_cause" in res_data and isinstance(res_data["root_cause"], str):
                root_causes = [res_data["root_cause"]] + fallback["root_causes"][1:]
            else:
                root_causes = fallback["root_causes"]
                
        corrective_actions = res_data.get("corrective_actions", [])
        if not isinstance(corrective_actions, list) or len(corrective_actions) == 0:
            if "corrective_action" in res_data and isinstance(res_data["corrective_action"], str):
                corrective_actions = [res_data["corrective_action"]] + fallback["corrective_actions"][1:]
            else:
                corrective_actions = fallback["corrective_actions"]
                
        corrective_action = res_data.get("corrective_action", corrective_actions[0] if corrective_actions else fallback["corrective_action"])
        
        output = {
            "root_causes": root_causes[:4],
            "root_cause": root_causes[0] if root_causes else "",
            "corrective_actions": corrective_actions[:4],
            "corrective_action": corrective_action
        }
        print(json.dumps(output))
    except Exception:
        fallback = generate_fallback_capa(finding)
        print(json.dumps(fallback))

if __name__ == "__main__":
    main()
