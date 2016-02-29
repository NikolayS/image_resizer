#!/bin/bash

# to run this scenario, RESIZERSERVICE should be defined (`export RESIZERSERVICE=https://your.resizer.host.name`)

http GET "$RESIZERSERVICE/?w=300&src=https://upload.wikimedia.org/wikipedia/commons/c/c2/Faust_bei_der_Arbeit.JPG" -h | grep -v "Date:"
