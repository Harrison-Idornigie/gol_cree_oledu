#!/usr/bin/env python3
"""
Script to check for duplicates in the Plains Cree words JSON file.
This script verifies data integrity and identifies any duplicate entries.
"""

import json
import sys
from collections import Counter

def load_words_file(file_path):
    """Load the Plains Cree words JSON file"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            return json.load(f)
    except FileNotFoundError:
        print(f"Error: File '{file_path}' not found.")
        sys.exit(1)
    except json.JSONDecodeError as e:
        print(f"Error: Invalid JSON in file '{file_path}': {e}")
        sys.exit(1)

def check_duplicates(words):
    """Check for duplicate entries in the words list"""
    
    # Extract all text values
    word_texts = [word.get('text', '') for word in words]
    
    # Count occurrences of each word
    word_counts = Counter(word_texts)
    
    # Find duplicates
    duplicates = {word: count for word, count in word_counts.items() if count > 1}
    
    # Statistics
    total_words = len(words)
    unique_words = len(word_counts)
    duplicate_count = len(duplicates)
    
    print("=== Plains Cree Words Duplicate Check ===")
    print(f"Total entries: {total_words}")
    print(f"Unique words: {unique_words}")
    print(f"Duplicate words found: {duplicate_count}")
    print()
    
    if duplicates:
        print("DUPLICATES FOUND:")
        print("-" * 40)
        for word, count in duplicates.items():
            print(f"'{word}' appears {count} times")
            
            # Show details of duplicate entries
            duplicate_entries = [i for i, w in enumerate(words) if w.get('text') == word]
            for idx in duplicate_entries:
                entry = words[idx]
                translation = entry.get('metadata', {}).get('translation', 'N/A')
                pos = entry.get('part_of_speech', 'N/A')
                print(f"  Entry {idx + 1}: {pos} - {translation}")
            print()
        
        return False
    else:
        print("✅ NO DUPLICATES FOUND - All words are unique!")
        return True

def check_json_structure(words):
    """Check for structural issues in the JSON"""
    
    print("\n=== JSON Structure Check ===")
    
    issues = []
    
    for i, word in enumerate(words):
        entry_num = i + 1
        
        # Check required fields
        if not word.get('text'):
            issues.append(f"Entry {entry_num}: Missing 'text' field")
        
        if not word.get('part_of_speech'):
            issues.append(f"Entry {entry_num}: Missing 'part_of_speech' field")
        
        if not word.get('metadata'):
            issues.append(f"Entry {entry_num}: Missing 'metadata' field")
        else:
            metadata = word['metadata']
            required_metadata = ['translation', 'syllabics', 'proficiency_level', 'cultural_context']
            
            for field in required_metadata:
                if not metadata.get(field):
                    issues.append(f"Entry {entry_num} ({word.get('text', 'unknown')}): Missing metadata.{field}")
    
    if issues:
        print(f"❌ STRUCTURAL ISSUES FOUND ({len(issues)} issues):")
        for issue in issues[:10]:  # Show first 10 issues
            print(f"  - {issue}")
        if len(issues) > 10:
            print(f"  ... and {len(issues) - 10} more issues")
        return False
    else:
        print("✅ JSON STRUCTURE IS VALID - All required fields present!")
        return True

def analyze_vocabulary_distribution(words):
    """Analyze the distribution of vocabulary by proficiency level and categories"""
    
    print("\n=== Vocabulary Analysis ===")
    
    # Count by proficiency level
    proficiency_counts = Counter()
    tag_counts = Counter()
    pos_counts = Counter()
    
    for word in words:
        metadata = word.get('metadata', {})
        
        # Proficiency level
        prof_level = metadata.get('proficiency_level', 'Unknown')
        proficiency_counts[prof_level] += 1
        
        # Tags
        tags = metadata.get('tags', [])
        for tag in tags:
            tag_counts[tag] += 1
        
        # Part of speech
        pos = word.get('part_of_speech', 'Unknown')
        pos_counts[pos] += 1
    
    print("Proficiency Level Distribution:")
    for level in ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Unknown']:
        count = proficiency_counts.get(level, 0)
        if count > 0:
            print(f"  {level}: {count} words")
    
    print(f"\nTop 10 Categories (by tags):")
    for tag, count in tag_counts.most_common(10):
        print(f"  {tag}: {count} words")
    
    print(f"\nPart of Speech Distribution:")
    for pos, count in pos_counts.most_common():
        print(f"  {pos}: {count} words")

def main():
    """Main function"""
    file_path = "backend/database/seeders/Tenant/data/plains_cree_words.json"
    
    print("Loading Plains Cree words file...")
    words = load_words_file(file_path)
    
    # Check for duplicates
    no_duplicates = check_duplicates(words)
    
    # Check JSON structure
    valid_structure = check_json_structure(words)
    
    # Analyze vocabulary
    analyze_vocabulary_distribution(words)
    
    # Summary
    print("\n" + "=" * 50)
    if no_duplicates and valid_structure:
        print("✅ ALL CHECKS PASSED - File is ready for use!")
        sys.exit(0)
    else:
        print("❌ ISSUES FOUND - Please review and fix before proceeding")
        sys.exit(1)

if __name__ == "__main__":
    main()
