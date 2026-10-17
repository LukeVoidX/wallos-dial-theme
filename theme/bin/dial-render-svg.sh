#!/bin/sh
set -eu
if [ "$(id -u)" -eq 0 ] || [ "$#" -ne 4 ]; then
  exit 78
fi
case "$3:$4" in *[!0-9:]*|:*) exit 78;; esac
if [ "$3" -lt 1 ] || [ "$3" -gt 1024 ] || [ "$4" -lt 1 ] || [ "$4" -gt 1024 ]; then
  exit 78
fi
ulimit -v 131072
exec timeout -s KILL 5 rsvg-convert --width "$3" --height "$4" --output "$2" "$1"
