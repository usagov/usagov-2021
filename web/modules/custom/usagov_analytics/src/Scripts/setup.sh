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
pip3 install --no-cache-dir -r requirements.txt

echo "Running Python script..."
output=$(python3 gsc_api_v3.py $1 $2 $3)

echo "$output"
echo "Python script finished."

# Deactivate virtual environment
deactivate
