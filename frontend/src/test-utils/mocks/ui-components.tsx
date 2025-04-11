import React from 'react';

// Mock UI components
export const Alert = ({ children, className }: { children: React.ReactNode, className?: string }) => (
  <div data-testid="mock-alert" className={className}>{children}</div>
);

export const AlertTitle = ({ children, className }: { children: React.ReactNode, className?: string }) => (
  <div data-testid="mock-alert-title" className={className}>{children}</div>
);

export const AlertDescription = ({ children, className }: { children: React.ReactNode, className?: string }) => (
  <div data-testid="mock-alert-description" className={className}>{children}</div>
);

export const Button = ({ 
  children, 
  onClick, 
  disabled, 
  className,
  variant,
  size
}: { 
  children: React.ReactNode, 
  onClick?: () => void, 
  disabled?: boolean, 
  className?: string,
  variant?: string,
  size?: string
}) => (
  <button 
    data-testid="mock-button" 
    onClick={onClick} 
    disabled={disabled} 
    className={className}
  >
    {children}
  </button>
);

// Add more UI component mocks as needed
