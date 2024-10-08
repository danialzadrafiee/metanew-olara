#!/bin/bash

# File: auction_spot_cron.sh

# Function to run the curl commands
run_commands() {
    curl https://mback.developerpie.com/api/cron/auctions/execute_all_auctions
    curl https://mback.developerpie.com/api/cron/spot/update_balances
}

# Function to start the loop
start_loop() {
    while true; do
        run_commands
        sleep 10
    done
}

# Function to check if the script is running
is_running() {
    if [ -f /tmp/auction_spot_cron.pid ]; then
        pid=$(cat /tmp/auction_spot_cron.pid)
        if ps -p $pid > /dev/null; then
            return 0
        fi
    fi
    return 1
}

# Start or stop based on the argument
case "$1" in
    start)
        if is_running; then
            echo "The script is already running."
            exit 1
        fi
        echo "Starting the script..."
        start_loop &
        echo $! > /tmp/auction_spot_cron.pid
        echo "Script started. PID: $(cat /tmp/auction_spot_cron.pid)"
        ;;
    stop)
        if is_running; then
            pid=$(cat /tmp/auction_spot_cron.pid)
            echo "Stopping the script (PID: $pid)..."
            kill $pid
            rm /tmp/auction_spot_cron.pid
            echo "Script stopped."
        else
            echo "The script is not running."
        fi
        ;;
    status)
        if is_running; then
            echo "The script is running. PID: $(cat /tmp/auction_spot_cron.pid)"
        else
            echo "The script is not running."
        fi
        ;;
    *)
        echo "Usage: $0 {start|stop|status}"
        exit 1
        ;;
esac

exit 0