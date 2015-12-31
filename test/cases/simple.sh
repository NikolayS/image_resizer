#!/bin/bash

# to run this scenario, COPIERSERVICE should be defined (`export COPIERSERVICE=https://your.copier.host.name`)

http GET "$RESIZERSERVICE/?w=300&src=https://upload.wikimedia.org/wikipedia/commons/c/c2/Faust_bei_der_Arbeit.JPG" -h | grep -v "Date:"
