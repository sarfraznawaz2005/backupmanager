#!/bin/sh
git status
git add .
read commitMessage
git commit -am "$commitMessage"
git push
git push --delete origin 1.0.0
git push origin 1.0.0
echo Press Enter...
read