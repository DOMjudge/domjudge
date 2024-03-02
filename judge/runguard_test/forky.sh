#!/usr/bin/bash

for (( i = 0; i < 32; i++ )); do
  echo $i
  sleep 5 &
done
