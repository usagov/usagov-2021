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
python3 -m pip install --upgrade pip setuptools wheel

# Install required packages
pip3 install -r requirements.txt

echo "Running Python script..."
output=$(python3 $1 $2)

echo $output

echo "Python script finished."

# Deactivate virtual environment
deactivate
