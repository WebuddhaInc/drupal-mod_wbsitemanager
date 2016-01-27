#!/bin/bash

# Testing

  # echo $(pwd)
  # echo $( cd $( dirname "${BASH_SOURCE[0]}" ) && pwd )
  # exit

# Joomla Path

  JPATH="$1"
  if [ ! -d "$JPATH" ]; then
    if [ -d "../../public_html/sites/all/modules" ]; then
      JPATH="../../public_html/"
    else
      echo "Invalid Drupal Path";
    fi
  fi

  if [[ ! $JPATH =~ [\/$] ]]; then
    JPATH="$JPATH/"
  fi

# Repository Path

  RPATH="$2"
  if [ ! -d "$RPATH" ]; then
    if [ -d "./module" ]; then
      RPATH="./"
    else
      echo "Invalid Repo Path";
    fi
  fi

  if [[ ! $RPATH =~ [\/$] ]]; then
    RPATH="$RPATH/"
  fi

# Absolute Path

  ARPATH=$( cd $RPATH && pwd )/
  AJPATH=$( cd $JPATH && pwd )/

# Echo

  echo "Repository path:"
  echo "$ARPATH"
  echo ""

  echo "Drupal path:"
  echo "$AJPATH"
  echo ""

  read -p "Press any key to continue... " -n1 -s
  echo

# remove

  rm $AJPATH/sites/all/modules/wbsitemanager
