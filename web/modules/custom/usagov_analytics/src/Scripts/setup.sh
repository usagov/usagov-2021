#!/bin/bash

# Check if virtual environment already exists
if [ ! -d "myenv" ]; then
  # Create virtual environment
  python3 -m venv myenv
  echo "Virtual environment 'myenv' created."
else
  echo "Virtual environment 'myenv' already exists."
fi

# Activate virtual environment
source myenv/bin/activate

# Upgrade pip
pip3 install --upgrade pip

# Install required packages
pip3 install -r requirements.txt

# Run the Python script
python3 gsc_api_v3.py

# Deactivate virtual environment
deactivate
