#!/bin/bash
program=http
condition=$(which $program 2>/dev/null | grep -v "not found" | wc -l)
if [ $condition -eq 0 ] ; then
    >&2 echo "\"$program\" tool is missing! install HTTPie (\"pip install --upgrade httpie\" or \"brew install httpie\")"
    exit
fi
if [ "$RESIZERSERVICE" == "" ] ; then
    >&2 echo "RESIZERSERVICE is missing (use \"export RESIZERSERVICE=https://your.resizer.hostname\", w/o trailing slash)"
    exit
fi
absAllowed=$(grep ALLOW_ABSOLUTE_URLS config.local.php | awk '{print tolower($3)}' | sed 's/\/\/.*//')
if [ "$absAllowed" != "true;" ] && [ "$absAllowed" != "1;" ] ; then
    >&2 echo "ALLOW_ABSOLUTE_URLS should be set to TRUE to enable automated tests. (Hint: edit config.local.php.)"
    exit
fi;

path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

for f in $(ls "$path/cases/"*.sh)
do
    casename=$(echo "$f" | sed s/\.sh// | sed s%"$path/cases/"%%)
    #echo "Processing test case: \"$casename\""
    #$f
    result=$(diff -w "$path/cases/$casename.expected" <($f))
    if [ "$result" != "" ]
    then
        >&2 echo "[$(date)] FAILED test case \"$casename\"! See STDOUT for details"
        echo "FAILED test case \"$casename\""
        echo "Details:"
        echo "--------------------"
        echo "$result"
        echo "--------------------"
    else
        echo "PASSED test case \"$casename\""
    fi
done

