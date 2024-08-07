#!/bin/sh

sqlite3 brc.db ".schema --indent" > ../migrations/schema.sql
