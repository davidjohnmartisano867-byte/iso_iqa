import os
import sys
import subprocess

if __name__ == "__main__":
    script_dir = os.path.dirname(os.path.abspath(__file__))
    target = os.path.join(script_dir, "scikit", "predict.py")
    subprocess.run([sys.executable, target])
