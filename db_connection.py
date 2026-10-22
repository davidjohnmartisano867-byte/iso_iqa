import sys
import mysql.connector
from mysql.connector import Error

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'iso_iqa',
    'port': 3306
}

def get_db_connection():
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        if connection.is_connected():
            return connection
    except Error as e:
        print(f"Error connecting to MySQL server: {e}", file=sys.stderr)
        return None

def test_connection():
    print("=" * 60)
    print("Testing connection to iso_iqa database...")
    print("=" * 60)
    
    connection = get_db_connection()
    if connection:
        try:
            db_info = connection.get_server_info()
            print(f"Successfully connected to MySQL Server version: {db_info}")
            
            cursor = connection.cursor()
            cursor.execute("SELECT DATABASE();")
            record = cursor.fetchone()
            print(f"Connected to database: {record[0]}")
            
            cursor.execute("SHOW TABLES;")
            tables = cursor.fetchall()
            print("\nAvailable tables in 'iso_iqa':")
            if tables:
                for table in tables:
                    print(f" - {table[0]}")
            else:
                print(" No tables found.")
                
            cursor.close()
        except Error as e:
            print(f"Error during query execution: {e}", file=sys.stderr)
        finally:
            connection.close()
            print("=" * 60)
            print("Database connection closed.")
            print("=" * 60)
    else:
        print("Failed to establish a database connection.", file=sys.stderr)
        print("=" * 60)

if __name__ == '__main__':
    test_connection()
