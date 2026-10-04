#!/bin/bash
# run_all_multi.sh — open each program in its own terminal

# Compile untitled.c first
gcc -o untitled untitled.c -lm
if [ $? -eq 0 ]; then
    echo "✅ Compilation successful."
else
    echo "❌ Compilation failed."
    exit 1
fi

# Run untitled.c in a new terminal
gnome-terminal -- bash -c "./untitled; exec bash"

# Run untitled.php in a new terminal
gnome-terminal -- bash -c "php /var/www/html/untitled.php; exec bash"

# Run alert.php every 5 seconds in a new terminal
gnome-terminal -- bash -c "while true; do php /var/www/html/alert.php; echo '---'; sleep 5; done; exec bash"
