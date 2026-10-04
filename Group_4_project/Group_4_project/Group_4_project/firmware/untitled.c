#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>
#include <time.h>
#include <unistd.h> // For sleep()

#define MAX_SAMPLES 100

// Helper function to get current date and time string
void get_current_timestamp(char *buffer, size_t max_size) {
    time_t raw_time;
    struct tm *time_info;

    time(&raw_time);
    time_info = localtime(&raw_time);

    // Formats as: [2026-05-19 10:35:12]
    strftime(buffer, max_size, "[%Y-%m-%d %H:%M:%S]", time_info);
}

int main() {
    const char *target_mac = "C4:BE:84:E1:1F:F9";
    const char *log_path = "/home/training/RSSI/btmgmt_rssi.log";

    // 1. Clear the old log file once on startup
    FILE *log_clear = fopen(log_path, "w");
    if (log_clear == NULL) {
        perror("Error: Could not open or clear log file. Check folder permissions.");
        return 1;
    }
    fclose(log_clear);

    char command[512];
    // Removed 'timeout 10s' to make the scanning continuous
    snprintf(command, sizeof(command), 
             "bluetoothctl scan on 2>&1 | grep --line-buffered -i '%s' | grep --line-buffered 'RSSI'", 
             target_mac);

    printf("Starting continuous scan. Press Ctrl+C to stop...\n");

    while (1) { // Infinite loop to keep acquisition alive continuously
        FILE *fp = popen(command, "r");
        if (fp == NULL) {
            perror("Failed to execute scan command. Retrying in 5 seconds...");
            sleep(5);
            continue;
        }

        char line[512];
        char time_str[64];

        // Process live streaming terminal lines
        while (fgets(line, sizeof(line), fp) != NULL) {
            line[strcspn(line, "\r\n")] = 0; // Strip raw newlines

            // Find where the readable text "Device..." actually starts, skipping ANSI escape sequences
            char *clean_line_start = strstr(line, "Device");
            if (clean_line_start == NULL) {
                continue; // Skip lines that don't format cleanly
            }

            // Get clean database-ready timestamp
            get_current_timestamp(time_str, sizeof(time_str));

            // 2. Open log for appending inside the stream loop
            FILE *log = fopen(log_path, "a");
            if (log != NULL) {
                // Writes cleanly: [2026-05-19 10:35:12] Device C4:BE:84:E1:1F:F9 RSSI: -68
                fprintf(log, "%s %s\n", time_str, clean_line_start);
                fclose(log);
            }

            // Optional: Print to console so you can watch live progress
            printf("%s %s\n", time_str, clean_line_start);
            
            // --- Live Real-Time Calculation Block ---
            char *rssi_ptr = strstr(clean_line_start, "RSSI:");
if (rssi_ptr != NULL) {
        int val = 0;
        if (sscanf(rssi_ptr, "RSSI: %d", &val) == 1) {
            // Reference RSSI at 1 meter (calibration value)
            double txPower = -60.0; 
            // Path loss exponent (depends on environment: 2=open space, 3=indoor, 4=obstructed)
            double n = 4.0;         

            // Log-distance path loss model
            double distance_meters = pow(10.0, (txPower - (double)val) / (10.0 * n));

            // Print results in meters
            printf("   -> Raw RSSI: %d dBm | Distance: %.2f m | Status: %s\n", 
                   val, distance_meters, (distance_meters < 3.0) ? "In range" : "Out of range!");
        }
    } else {
        printf("No RSSI data found in line.\n");
    }
        }
        pclose(fp);
        sleep(2); // Safeguard cooldown before auto-restarting pipeline if dropped
    }

    return 0;
}
