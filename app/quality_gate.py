def validate_article_content(title: str, content: str, keyword: str) -> dict:
    errors = []
    word_count = len(content.split())
    
    if word_count < 400:
        errors.append(f"Indholdet er for kort ({word_count} ord, minimum 400 krævet).")
    if keyword.lower() not in content.lower():
        errors.append(f"Fokus-keyword '{keyword}' mangler i teksten.")
        
    return {
        "passed": len(errors) == 0,
        "word_count": word_count,
        "errors": errors
    }
