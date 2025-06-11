import React, { ReactElement } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import { User, UserType } from '@/types/tenant/user';

// Mock user data
export const mockUser: User = {
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  membership: UserType.USER,
  is_active: true,
  created_at: '2023-01-01T00:00:00.000Z',
  updated_at: '2023-01-01T00:00:00.000Z',
  email_verified_at: null,
};

export const mockVerifiedUser: User = {
  ...mockUser,
  email_verified_at: '2023-01-01T00:00:00.000Z',
};

// Mock auth context
export const mockAuthContext = {
  user: mockUser,
  setUser: vi.fn(),
  isLoading: false,
};

export const mockVerifiedAuthContext = {
  user: mockVerifiedUser,
  setUser: vi.fn(),
  isLoading: false,
};

// Mock UI components
export const MockButton = ({ 
  children, 
  onClick, 
  disabled, 
  className 
}: { 
  children: React.ReactNode, 
  onClick?: () => void, 
  disabled?: boolean, 
  className?: string 
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

export const MockAlert = ({ 
  children, 
  className 
}: { 
  children: React.ReactNode, 
  className?: string 
}) => (
  <div data-testid="mock-alert" className={className}>
    {children}
  </div>
);

export const MockAlertTitle = ({ 
  children, 
  className 
}: { 
  children: React.ReactNode, 
  className?: string 
}) => (
  <div data-testid="mock-alert-title" className={className}>
    {children}
  </div>
);

export const MockAlertDescription = ({ 
  children, 
  className 
}: { 
  children: React.ReactNode, 
  className?: string 
}) => (
  <div data-testid="mock-alert-description" className={className}>
    {children}
  </div>
);

export const MockCard = ({ 
  children, 
  className 
}: { 
  children: React.ReactNode, 
  className?: string 
}) => (
  <div data-testid="mock-card" className={className}>
    {children}
  </div>
);

export const MockInput = ({ 
  id,
  type,
  value,
  onChange,
  required,
  className,
  placeholder
}: { 
  id?: string,
  type?: string,
  value?: string,
  onChange?: (e: any) => void,
  required?: boolean,
  className?: string,
  placeholder?: string
}) => (
  <input 
    data-testid="mock-input" 
    id={id}
    type={type}
    value={value}
    onChange={onChange}
    required={required}
    className={className}
    placeholder={placeholder}
  />
);

// Custom render with providers
const customRender = (
  ui: ReactElement,
  options?: Omit<RenderOptions, 'wrapper'>,
) => render(ui, { ...options });

export * from '@testing-library/react';
export { customRender as render };
